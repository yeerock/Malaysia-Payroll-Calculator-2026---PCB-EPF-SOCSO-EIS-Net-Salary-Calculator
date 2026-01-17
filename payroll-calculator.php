<?php
/**
 * Standalone Payroll Calculator - Malaysia 2026
 * Calculates PCB, EPF, SOCSO, EIS and Net Salary
 * Uses the same JSON files: epf.json, socso.json, eis.json, pcb.json
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

define('COMPANY_NAME', 'VYROX INTERNATIONAL SDN BHD');

$TAX_CATEGORIES = [0 => 'Single'] + 
    array_combine(range(1, 11), array_map(fn($i) => "Spouse not working, " . ($i-1 ?: "No") . " child" . ($i-1 > 1 ? "ren" : ($i-1 ? "" : "")), range(1, 11))) +
    array_combine(range(12, 22), array_map(fn($i) => "Spouse working, " . ($i-12 ?: "No") . " child" . ($i-12 > 1 ? "ren" : ($i-12 ? "" : "")), range(12, 22)));

// ============================================================================
// EPF CALCULATION
// ============================================================================
function getEPFTable() {
    static $epfTable = null;
    if ($epfTable === null) {
        $jsonPath = __DIR__ . '/epf.json';
        if (file_exists($jsonPath)) {
            $epfTable = json_decode(file_get_contents($jsonPath), true);
        } else {
            $epfTable = [];
        }
    }
    return $epfTable;
}

function calculateEPF($salary, $nationality, $age) {
    if ($salary <= 0) {
        return ['employee_rate' => 0, 'employee_amount' => 0, 'employer_rate' => 0, 'employer_amount' => 0];
    }
    
    $epfTable = getEPFTable();
    if (empty($epfTable)) {
        return ['employee_rate' => 0, 'employee_amount' => 0, 'employer_rate' => 0, 'employer_amount' => 0];
    }
    
    // Non-Malaysian workers: Use rates from JSON (mandatory from Oct 2026)
    if ($nationality === 'Non-Malaysian') {
        $foreignRates = $epfTable['foreign_worker'] ?? null;
        
        if ($foreignRates) {
            $rateKey = $age >= 60 ? 'age_60_above' : 'below_60';
            $rates = $foreignRates[$rateKey] ?? $foreignRates['below_60'] ?? null;
            
            if ($rates) {
                $employeeRate = $rates['employee_rate'] ?? 2;
                $employerRate = $rates['employer_rate'] ?? 2;
                $employeeAmount = round($salary * $employeeRate / 100, 0);
                $employerAmount = round($salary * $employerRate / 100, 0);
                return [
                    'employee_rate' => $employeeRate, 
                    'employee_amount' => $employeeAmount, 
                    'employer_rate' => $employerRate, 
                    'employer_amount' => $employerAmount
                ];
            }
        }
        
        // Fallback to default 2% + 2% if JSON not configured
        $employeeRate = 2;
        $employerRate = 2;
        $employeeAmount = round($salary * $employeeRate / 100, 0);
        $employerAmount = round($salary * $employerRate / 100, 0);
        return [
            'employee_rate' => $employeeRate, 
            'employee_amount' => $employeeAmount, 
            'employer_rate' => $employerRate, 
            'employer_amount' => $employerAmount
        ];
    }
    
    // Malaysian age 60+
    if ($age >= 60) {
        $part = $epfTable['part_e'] ?? null;
        if ($part && isset($part['brackets'])) {
            if ($salary > 20000) {
                $employerRate = $part['above_20000']['employer_rate'];
                $employeeRate = $part['above_20000']['employee_rate'];
                $employerAmount = ceil($salary * $employerRate / 100);
                $employeeAmount = ceil($salary * $employeeRate / 100);
                return ['employee_rate' => $employeeRate, 'employee_amount' => $employeeAmount, 'employer_rate' => $employerRate, 'employer_amount' => $employerAmount];
            }
            foreach ($part['brackets'] as $bracket) {
                if ($salary <= $bracket['max']) {
                    return [
                        'employee_rate' => 0,
                        'employee_amount' => $bracket['employee'],
                        'employer_rate' => 4,
                        'employer_amount' => $bracket['employer']
                    ];
                }
            }
        }
        $employerAmount = ceil($salary * 0.04);
        return ['employee_rate' => 0, 'employee_amount' => 0, 'employer_rate' => 4, 'employer_amount' => $employerAmount];
    }
    
    // Malaysian under 60
    $part = $epfTable['part_a'] ?? null;
    if ($part && isset($part['brackets'])) {
        if ($salary > 20000) {
            $employerRate = $part['above_20000']['employer_rate'];
            $employeeRate = $part['above_20000']['employee_rate'];
            $employerAmount = ceil($salary * $employerRate / 100);
            $employeeAmount = ceil($salary * $employeeRate / 100);
            return ['employee_rate' => $employeeRate, 'employee_amount' => $employeeAmount, 'employer_rate' => $employerRate, 'employer_amount' => $employerAmount];
        }
        foreach ($part['brackets'] as $bracket) {
            if ($salary <= $bracket['max']) {
                return [
                    'employee_rate' => 11,
                    'employee_amount' => $bracket['employee'],
                    'employer_rate' => $salary <= 5000 ? 13 : 12,
                    'employer_amount' => $bracket['employer']
                ];
            }
        }
    }
    
    // Fallback calculation
    if ($salary <= 5000) {
        $upper = ceil($salary / 20) * 20;
        return ['employee_rate' => 11, 'employee_amount' => ceil($upper * 0.11), 'employer_rate' => 13, 'employer_amount' => ceil($upper * 0.13)];
    }
    return ['employee_rate' => 11, 'employee_amount' => round($salary * 0.11, 2), 'employer_rate' => 12, 'employer_amount' => round($salary * 0.12, 2)];
}

// ============================================================================
// SOCSO CALCULATION
// ============================================================================
function getSOCSOTable() {
    static $socsoTable = null;
    if ($socsoTable === null) {
        $jsonPath = __DIR__ . '/socso.json';
        if (file_exists($jsonPath)) {
            $socsoTable = json_decode(file_get_contents($jsonPath), true);
        } else {
            $socsoTable = [];
        }
    }
    return $socsoTable;
}

function calculateSOCSO($salary, $age, $category) {
    // No contribution cases
    if ($salary <= 0 || $category === 'No Contribution') {
        return ['employer' => 0, 'employee' => 0];
    }
    
    // Age 60+ MUST be Category 2 (Injury Only) - override any passed category
    if ($age >= 60) {
        $category = 'Category 2 (Injury Only)';
    }
    
    $socsoTable = getSOCSOTable();
    
    // Check for both old and new category formats
    $isCategory1 = ($category === 'Category 1' || $category === 'Category 1 (Injury + Invalidity)');
    $categoryKey = $isCategory1 ? 'category_1' : 'category_2';
    
    if (empty($socsoTable) || !isset($socsoTable[$categoryKey])) {
        // Fallback rates based on max wage RM6,000 (Oct 2024 rates)
        if ($isCategory1) {
            return ['employer' => 104.15, 'employee' => 29.75];
        } else {
            return ['employer' => 74.40, 'employee' => 0];
        }
    }
    
    $part = $socsoTable[$categoryKey];
    $maxWage = $part['max_wage'] ?? 6000;
    
    // Cap salary at max wage for contribution calculation
    $cappedSalary = min($salary, $maxWage);
    
    if (isset($part['brackets'])) {
        foreach ($part['brackets'] as $bracket) {
            if ($cappedSalary <= $bracket['max']) {
                return [
                    'employer' => $bracket['employer'],
                    'employee' => $isCategory1 ? $bracket['employee'] : 0
                ];
            }
        }
    }
    
    if (isset($part['above_max'])) {
        return [
            'employer' => $part['above_max']['employer'],
            'employee' => $isCategory1 ? $part['above_max']['employee'] : 0
        ];
    }

    // Final fallback
    if ($isCategory1) {
        return ['employer' => 104.15, 'employee' => 29.75];
    } else {
        return ['employer' => 74.40, 'employee' => 0];
    }
}

// ============================================================================
// EIS CALCULATION
// ============================================================================
function getEISTable() {
    static $eisTable = null;
    if ($eisTable === null) {
        $jsonPath = __DIR__ . '/eis.json';
        if (file_exists($jsonPath)) {
            $eisTable = json_decode(file_get_contents($jsonPath), true);
        } else {
            $eisTable = [];
        }
    }
    return $eisTable;
}

function calculateEIS($salary, $age, $nationality = 'Malaysian') {
    $eisTable = getEISTable();
    
    // Check if non-Malaysian workers are exempt (from JSON config)
    $foreignExempt = $eisTable['foreign_worker_exempt'] ?? true;
    if ($nationality === 'Non-Malaysian' && $foreignExempt) {
        return ['employer' => 0, 'employee' => 0];
    }
    
    $maxAge = $eisTable['max_age'] ?? 60;
    
    if ($age >= $maxAge || $salary <= 0) {
        return ['employer' => 0, 'employee' => 0];
    }
    
    if (empty($eisTable) || !isset($eisTable['brackets'])) {
        if ($salary > 6000) {
            return ['employer' => 11.90, 'employee' => 11.90];
        }
        return ['employer' => 11.90, 'employee' => 11.90];
    }
    
    $maxWage = $eisTable['max_wage'] ?? 6000;
    
    if ($salary > $maxWage) {
        $aboveMax = $eisTable['above_max']['contribution'] ?? 11.90;
        return ['employer' => $aboveMax, 'employee' => $aboveMax];
    }
    
    foreach ($eisTable['brackets'] as $bracket) {
        if ($salary <= $bracket['max']) {
            $contribution = $bracket['contribution'];
            return [
                'employer' => $contribution,
                'employee' => $contribution
            ];
        }
    }
    
    $aboveMax = $eisTable['above_max']['contribution'] ?? 11.90;
    return ['employer' => $aboveMax, 'employee' => $aboveMax];
}

// ============================================================================
// PCB TAX CALCULATION
// ============================================================================
function getPCBTable() {
    static $pcbTable = null;
    if ($pcbTable === null) {
        $jsonPath = __DIR__ . '/pcb.json';
        if (file_exists($jsonPath)) {
            $pcbTable = json_decode(file_get_contents($jsonPath), true);
        } else {
            $pcbTable = [];
        }
    }
    return $pcbTable;
}

function calculatePCB($grossSalary, $taxResident, $taxCategory, $nationality = 'Malaysian', $epfContribution = null) {
    if ($grossSalary <= 0) return 0;
    
    $pcbTable = getPCBTable();
    
    // Non-resident: flat rate tax
    if ($taxResident === 'No') {
        $rate = !empty($pcbTable) ? ($pcbTable['non_resident_rate'] ?? 30) : 30;
        $rawPcb = $grossSalary * $rate / 100;
        // Round to nearest 0.05, handling floating point precision
        $pcb = round(round($rawPcb * 20) / 20, 2);
        return $pcb;
    }
    
    // Resident: progressive tax with reliefs
    $reliefs = !empty($pcbTable['reliefs']) ? $pcbTable['reliefs'] : [
        'individual' => 9000,
        'spouse_not_working' => 4000,
        'child_under_18' => 2000
    ];
    $epfMaxRelief = $pcbTable['epf_max_relief'] ?? 4000;
    $minimumPcb = $pcbTable['minimum_pcb'] ?? 0;
    
    $annual = $grossSalary * 12;
    
    // EPF relief based on actual EPF contribution (if provided), otherwise estimate
    if ($epfContribution !== null) {
        // Use actual EPF contribution for relief (annualized)
        $epfRelief = min($epfContribution * 12, $epfMaxRelief);
    } else {
        // Fallback: estimate based on nationality (for backwards compatibility)
        $epfRate = ($nationality === 'Foreign') ? 0.02 : 0.11;
        $epfRelief = min($annual * $epfRate, $epfMaxRelief);
    }
    
    $individualRelief = $reliefs['individual'];
    
    // Spouse relief (only for categories 1-11: spouse not working)
    $spouseRelief = ($taxCategory >= 1 && $taxCategory <= 11) ? $reliefs['spouse_not_working'] : 0;
    
    // Children relief
    if ($taxCategory >= 1 && $taxCategory <= 11) {
        $numChildren = $taxCategory - 1;
    } elseif ($taxCategory >= 12 && $taxCategory <= 22) {
        $numChildren = $taxCategory - 12;
    } else {
        $numChildren = 0;
    }
    $childrenRelief = $numChildren * $reliefs['child_under_18'];
    
    $totalRelief = $epfRelief + $individualRelief + $spouseRelief + $childrenRelief;
    
    $chargeable = $annual - $totalRelief;
    
    if ($chargeable <= 5000) return 0;
    
    $tax = 0;
    $brackets = $pcbTable['tax_brackets'] ?? null;
    
    if ($brackets) {
        foreach ($brackets as $i => $bracket) {
            $bracketMin = $bracket['min'];
            $bracketMax = $bracket['max'] ?? PHP_INT_MAX;
            $rate = $bracket['rate'];
            $cumulativeTax = $bracket['cumulative_tax'];
            
            if ($chargeable <= $bracketMax) {
                $prevMax = $i > 0 ? $brackets[$i - 1]['max'] : 0;
                $taxableInBracket = $chargeable - $prevMax;
                $tax = $cumulativeTax + ($taxableInBracket * $rate / 100);
                break;
            }
        }
    } else {
        // Fallback tax brackets
        $fallbackBrackets = [
            [5000, 0, 0], [20000, 1, 0], [35000, 3, 150], [50000, 6, 600],
            [70000, 11, 1500], [100000, 19, 3700], [400000, 25, 9400],
            [600000, 26, 84400], [2000000, 28, 136400], [PHP_INT_MAX, 30, 528400]
        ];
        $prevMax = 0;
        foreach ($fallbackBrackets as $b) {
            if ($chargeable <= $b[0]) {
                $tax = $b[2] + (($chargeable - $prevMax) * $b[1] / 100);
                break;
            }
            $prevMax = $b[0];
        }
    }
    
    // Tax rebate for low income
    $rebate = 0;
    if ($chargeable <= 35000) {
        $rebate = 400;
        if ($taxCategory >= 1 && $taxCategory <= 11) {
            $rebate = 800;
        }
    }
    
    $tax = max(0, $tax - $rebate);
    
    // Monthly PCB (rounded to nearest 0.05)
    $monthly = $tax / 12;
    $monthly = round(ceil($monthly * 20) / 20, 2);
    
    return $monthly;
}

// ============================================================================
// API ENDPOINT HANDLER
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'calculate_statutory_preview') {
        $gross = floatval($_POST['gross_salary'] ?? 0);
        $nationality = $_POST['nationality'] ?? 'Malaysian';
        $age = intval($_POST['age'] ?? 0);
        $socsoCategory = $_POST['socso_category'] ?? 'Category 1 (Injury + Invalidity)';
        $taxResident = $_POST['tax_resident'] ?? 'Yes';
        $taxCategory = intval($_POST['tax_category'] ?? 0);
        $payslipType = $_POST['payslip_type'] ?? 'Employee Salary';
        
        // Define zero values for non-employee payslip types
        $zeroEpf = ['employee_rate' => 0, 'employee_amount' => 0, 'employer_rate' => 0, 'employer_amount' => 0];
        $zeroContrib = ['employee' => 0, 'employer' => 0];
        
        // Intern Wages: No statutory contributions at all
        if ($payslipType === 'Intern Wages') {
            echo json_encode([
                'success' => true,
                'epf' => $zeroEpf,
                'socso' => $zeroContrib,
                'eis' => $zeroContrib,
                'pcb' => 0
            ]);
            exit;
        }
        
        // Director Fee: No EPF/SOCSO/EIS, but PCB still applies
        if ($payslipType === 'Director Fee') {
            $pcb = calculatePCB($gross, $taxResident, $taxCategory, $nationality, 0);
            echo json_encode([
                'success' => true,
                'epf' => $zeroEpf,
                'socso' => $zeroContrib,
                'eis' => $zeroContrib,
                'pcb' => $pcb
            ]);
            exit;
        }
        
        // Employee Salary: Full statutory calculations
        $epf = calculateEPF($gross, $nationality, $age);
        
        echo json_encode([
            'success' => true,
            'epf' => $epf,
            'socso' => calculateSOCSO($gross, $age, $socsoCategory),
            'eis' => calculateEIS($gross, $age, $nationality),
            'pcb' => calculatePCB($gross, $taxResident, $taxCategory, $nationality, $epf['employee_amount'])
        ]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PCB Calculator Malaysia 2026 | EPF, SOCSO, EIS & Income Tax Calculator | Payroll Salary Calculator</title>
    <meta name="description" content="Free Malaysia payroll calculator for 2026. Calculate PCB (MTD), EPF/KWSP, SOCSO/PERKESO, EIS/SIP contributions and net salary. Accurate income tax calculator for employees, directors, and interns.">
    <meta name="keywords" content="PCB calculator, Malaysia income tax calculator, EPF calculator, SOCSO calculator, EIS calculator, payroll calculator Malaysia, salary calculator, MTD calculator, KWSP calculator, PERKESO calculator">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?') ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="Malaysia Payroll Calculator 2026 - PCB, EPF, SOCSO, EIS">
    <meta property="og:description" content="Free payroll calculator for Malaysia. Calculate all statutory contributions including PCB tax, EPF, SOCSO, and EIS.">
    <meta property="og:type" content="website">
    
    <!-- Schema.org structured data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "Malaysia Payroll Calculator",
        "description": "Calculate PCB, EPF, SOCSO, EIS and net salary for Malaysian payroll",
        "applicationCategory": "FinanceApplication",
        "operatingSystem": "Web Browser",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "MYR"
        }
    }
    </script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>[v-cloak]{display:none}</style>
</head>
<body class="bg-gray-50">
<div id="calculator" v-cloak class="min-h-screen py-4 sm:py-6 px-3 sm:px-4">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-4">
            <div class="bg-blue-600 text-white">
                <div class="relative px-4 pt-3 pb-2 min-h-[44px] flex items-center justify-center">
                    <h1 class="text-base sm:text-lg font-semibold">Payroll Calculator</h1>
                </div>
                <div class="px-4 pb-4 text-center">
                    <p class="text-blue-200 text-xs sm:text-sm">PCB, EPF, SOCSO, EIS & Net Salary Calculator Malaysia 2026</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Input Section -->
            <div class="bg-white rounded-lg shadow p-4 space-y-4">
                <h2 class="font-bold text-gray-700 border-b pb-2">Employee Information</h2>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Payslip Type</label>
                        <select v-model="form.payslip_type" @change="calculate" class="w-full p-2 border rounded text-sm">
                            <option>Employee Salary</option>
                            <option>Director Fee</option>
                            <option>Intern Wages</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Nationality</label>
                        <select v-model="form.nationality" @change="calculate" class="w-full p-2 border rounded text-sm">
                            <option>Malaysian</option>
                            <option>Non-Malaysian</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Age (Years)</label>
                        <input v-model.number="form.age" @input="onAgeChange" type="number" min="16" max="80" class="w-full p-2 border rounded text-sm" placeholder="e.g. 30">
                        <span v-if="form.age >= 60" class="text-xs text-orange-600">Age 60+: SOCSO Category 2, No EIS</span>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Working Days/Month</label>
                        <input v-model.number="form.working_days" @input="calculate" type="number" step="0.5" min="1" max="31" class="w-full p-2 border rounded text-sm">
                    </div>
                </div>

                <div class="border-t pt-3">
                    <h3 class="font-bold text-gray-700 text-sm mb-2">Tax Settings</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tax Resident?</label>
                            <select v-model="form.tax_resident" @change="calculate" class="w-full p-2 border rounded text-sm">
                                <option value="Yes">Yes (Progressive 0-30%)</option>
                                <option value="No">No (Flat 30%)</option>
                            </select>
                        </div>
                        <div v-if="form.tax_resident === 'Yes'">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tax Category</label>
                            <select v-model.number="form.tax_category" @change="calculate" class="w-full p-2 border rounded text-sm">
                                <option v-for="(label, value) in taxCategories" :key="value" :value="parseInt(value)">{{ label }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div v-if="form.payslip_type === 'Employee Salary'" class="border-t pt-3">
                    <h3 class="font-bold text-gray-700 text-sm mb-2">SOCSO Category</h3>
                    <select v-model="form.socso_category" @change="calculate" :disabled="form.age >= 60" class="w-full p-2 border rounded text-sm" :class="{'bg-yellow-50': form.age >= 60}">
                        <option>Category 1 (Injury + Invalidity)</option>
                        <option>Category 2 (Injury Only)</option>
                        <option>No Contribution</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        <span v-if="form.age >= 60">Age 60+: Category 2 mandatory (Injury Only)</span>
                        <span v-else-if="form.socso_category === 'Category 1 (Injury + Invalidity)'">Both employer & employee contribute</span>
                        <span v-else-if="form.socso_category === 'Category 2 (Injury Only)'">Employer only, no employee contribution</span>
                        <span v-else>No SOCSO contributions</span>
                    </p>
                </div>

                <div class="border-t pt-3">
                    <h3 class="font-bold text-gray-700 text-sm mb-2">Earnings</h3>
                    <div class="space-y-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ form.payslip_type === 'Director Fee' ? 'Director Fee' : (form.payslip_type === 'Intern Wages' ? 'Intern Wages' : 'Basic Salary') }} (RM)</label>
                            <input v-model.number="form.basic_salary" @input="calculate" type="number" step="0.01" min="0" class="w-full p-2 border rounded text-sm" placeholder="0.00">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Allowances</label>
                            <div v-for="(a, i) in form.allowances" :key="i" class="flex gap-2 mb-2">
                                <input v-model="a.name" @input="calculate" type="text" placeholder="Allowance name" class="flex-1 p-2 border rounded text-sm">
                                <input v-model.number="a.amount" @input="calculate" type="number" step="0.01" placeholder="0.00" class="w-24 p-2 border rounded text-sm">
                                <button type="button" @click="form.allowances.splice(i,1); calculate()" class="text-red-600 text-xl px-2">&times;</button>
                            </div>
                            <button type="button" @click="form.allowances.push({name:'',amount:0})" class="w-full p-2 bg-green-50 border border-green-200 rounded text-xs hover:bg-green-100">+ Add Allowance</button>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Overtime</label>
                            <div v-for="(o, i) in form.overtime" :key="i" class="grid grid-cols-12 gap-1 mb-2">
                                <input v-model.number="o.hours" @input="calcOT(i)" type="number" step="0.5" placeholder="Hrs" class="col-span-3 p-2 border rounded text-xs">
                                <select v-model="o.multiplier" @change="calcOT(i)" class="col-span-3 p-2 border rounded text-xs">
                                    <option value="1.5">1.5x</option>
                                    <option value="2">2x</option>
                                    <option value="3">3x</option>
                                </select>
                                <input v-model.number="o.rate" @input="calcOT(i)" type="number" step="0.01" placeholder="Rate" class="col-span-3 p-2 border rounded text-xs">
                                <span class="col-span-2 p-2 bg-gray-50 rounded text-xs text-right">{{ o.amount.toFixed(2) }}</span>
                                <button type="button" @click="form.overtime.splice(i,1); calculate()" class="col-span-1 text-red-600">&times;</button>
                            </div>
                            <button type="button" @click="addOT()" class="w-full p-2 bg-green-50 border border-green-200 rounded text-xs hover:bg-green-100">+ Add Overtime</button>
                        </div>
                    </div>
                </div>

                <div class="border-t pt-3">
                    <h3 class="font-bold text-gray-700 text-sm mb-2">Deductions</h3>
                    <div class="space-y-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Unpaid Leave</label>
                            <div v-for="(u, i) in form.unpaid_leaves" :key="i" class="flex gap-2 mb-2">
                                <input v-model.number="u.days" @input="calcLeave(i)" type="number" step="0.5" placeholder="Days" class="w-20 p-2 border rounded text-sm">
                                <span class="p-2 bg-gray-50 rounded text-sm flex-1 text-right">RM {{ u.amount.toFixed(2) }}</span>
                                <button type="button" @click="form.unpaid_leaves.splice(i,1); calculate()" class="text-red-600 text-xl px-2">&times;</button>
                            </div>
                            <button type="button" @click="addLeave()" class="w-full p-2 bg-orange-50 border border-orange-200 rounded text-xs hover:bg-orange-100">+ Add Unpaid Leave</button>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Other Deductions</label>
                            <div v-for="(d, i) in form.deductions" :key="i" class="flex gap-2 mb-2">
                                <input v-model="d.name" @input="calculate" type="text" placeholder="Deduction name" class="flex-1 p-2 border rounded text-sm">
                                <input v-model.number="d.amount" @input="calculate" type="number" step="0.01" placeholder="0.00" class="w-24 p-2 border rounded text-sm">
                                <button type="button" @click="form.deductions.splice(i,1); calculate()" class="text-red-600 text-xl px-2">&times;</button>
                            </div>
                            <button type="button" @click="form.deductions.push({name:'',amount:0})" class="w-full p-2 bg-orange-50 border border-orange-200 rounded text-xs hover:bg-orange-100">+ Add Deduction</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Section -->
            <div class="space-y-4">
                <!-- Summary Cards -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-blue-600 text-white p-4 rounded-lg">
                        <div class="text-xs font-medium opacity-80">NET SALARY</div>
                        <div class="text-2xl font-bold">RM {{ result.net_salary.toFixed(2) }}</div>
                    </div>
                    <div class="bg-orange-600 text-white p-4 rounded-lg">
                        <div class="text-xs font-medium opacity-80">EMPLOYER COST</div>
                        <div class="text-2xl font-bold">RM {{ result.employer_cost.toFixed(2) }}</div>
                    </div>
                </div>

                <!-- Detailed Breakdown -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="font-bold text-gray-700 border-b pb-2 mb-3">Calculation Breakdown</h3>
                    
                    <!-- Earnings -->
                    <div class="mb-4">
                        <div class="flex justify-between items-center bg-green-50 p-2 rounded mb-2">
                            <span class="font-medium text-green-800 text-sm">EARNINGS</span>
                            <span class="font-bold text-green-800">RM {{ result.gross_salary.toFixed(2) }}</span>
                        </div>
                        <div class="space-y-1 text-sm pl-2">
                            <div class="flex justify-between"><span>{{ form.payslip_type === 'Director Fee' ? 'Director Fee' : (form.payslip_type === 'Intern Wages' ? 'Intern Wages' : 'Basic Salary') }}</span><span>{{ form.basic_salary.toFixed(2) }}</span></div>
                            <div class="flex justify-between"><span>Total Allowances</span><span>{{ result.total_allowances.toFixed(2) }}</span></div>
                            <div class="flex justify-between"><span>Total Overtime</span><span>{{ result.total_overtime.toFixed(2) }}</span></div>
                        </div>
                    </div>

                    <!-- Statutory Deductions -->
                    <div v-if="form.payslip_type === 'Employee Salary'" class="mb-4">
                        <div class="flex justify-between items-center bg-red-50 p-2 rounded mb-2">
                            <span class="font-medium text-red-800 text-sm">STATUTORY DEDUCTIONS</span>
                            <span class="font-bold text-red-800">RM {{ result.total_statutory_employee.toFixed(2) }}</span>
                        </div>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-500">
                                    <th class="text-left pb-1"></th>
                                    <th class="text-right pb-1">Employer</th>
                                    <th class="text-right pb-1">Employee</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <tr>
                                    <td>EPF ({{ result.epf_employer_rate }}% / {{ result.epf_employee_rate }}%)</td>
                                    <td class="text-right text-orange-600">{{ result.epf_employer.toFixed(2) }}</td>
                                    <td class="text-right text-blue-600">{{ result.epf_employee.toFixed(2) }}</td>
                                </tr>
                                <tr>
                                    <td>SOCSO</td>
                                    <td class="text-right text-orange-600">{{ result.socso_employer.toFixed(2) }}</td>
                                    <td class="text-right text-blue-600">{{ result.socso_employee.toFixed(2) }}</td>
                                </tr>
                                <tr>
                                    <td>EIS <span v-if="form.nationality === 'Non-Malaysian'" class="text-gray-400">(N/A)</span></td>
                                    <td class="text-right text-orange-600">{{ result.eis_employer.toFixed(2) }}</td>
                                    <td class="text-right text-blue-600">{{ result.eis_employee.toFixed(2) }}</td>
                                </tr>
                                <tr class="border-t">
                                    <td class="font-medium pt-1">Subtotal</td>
                                    <td class="text-right font-medium text-orange-600 pt-1">{{ result.total_statutory_employer.toFixed(2) }}</td>
                                    <td class="text-right font-medium text-blue-600 pt-1">{{ (result.epf_employee + result.socso_employee + result.eis_employee).toFixed(2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- PCB Tax -->
                    <div class="mb-4">
                        <div class="flex justify-between items-center bg-yellow-50 p-2 rounded mb-2">
                            <span class="font-medium text-yellow-800 text-sm">PCB TAX (MTD)</span>
                            <span class="font-bold text-yellow-800">RM {{ result.pcb_tax.toFixed(2) }}</span>
                        </div>
                        <div class="text-xs text-gray-500 pl-2">
                            <span v-if="form.tax_resident === 'No'">Non-resident: Flat 30% rate</span>
                            <span v-else>Resident: Progressive rate with reliefs applied</span>
                        </div>
                    </div>

                    <!-- Other Deductions -->
                    <div class="mb-4">
                        <div class="flex justify-between items-center bg-orange-50 p-2 rounded mb-2">
                            <span class="font-medium text-orange-800 text-sm">OTHER DEDUCTIONS</span>
                            <span class="font-bold text-orange-800">RM {{ result.total_other_deductions.toFixed(2) }}</span>
                        </div>
                        <div class="space-y-1 text-sm pl-2">
                            <div class="flex justify-between"><span>Unpaid Leave</span><span>{{ result.total_unpaid_leave.toFixed(2) }}</span></div>
                            <div class="flex justify-between"><span>Other Deductions</span><span>{{ result.total_deductions.toFixed(2) }}</span></div>
                        </div>
                    </div>

                    <!-- Final Summary -->
                    <div class="border-t pt-3 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span>Gross Salary</span>
                            <span class="font-medium">RM {{ result.gross_salary.toFixed(2) }}</span>
                        </div>
                        <div v-if="form.payslip_type === 'Employee Salary'" class="flex justify-between text-sm text-red-600">
                            <span>Less: Statutory (Employee)</span>
                            <span>({{ result.total_statutory_employee.toFixed(2) }})</span>
                        </div>
                        <div class="flex justify-between text-sm text-yellow-600">
                            <span>Less: PCB Tax</span>
                            <span>({{ result.pcb_tax.toFixed(2) }})</span>
                        </div>
                        <div class="flex justify-between text-sm text-orange-600">
                            <span>Less: Other Deductions</span>
                            <span>({{ result.total_other_deductions.toFixed(2) }})</span>
                        </div>
                        <div class="flex justify-between font-bold text-lg border-t pt-2">
                            <span>Net Salary</span>
                            <span class="text-green-600">RM {{ result.net_salary.toFixed(2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Employer Cost Breakdown -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="font-bold text-gray-700 border-b pb-2 mb-3">Total Employer Cost</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span>Gross Salary</span><span>RM {{ result.gross_salary.toFixed(2) }}</span></div>
                        <div v-if="form.payslip_type === 'Employee Salary'" class="flex justify-between text-orange-600"><span>+ Employer EPF</span><span>RM {{ result.epf_employer.toFixed(2) }}</span></div>
                        <div v-if="form.payslip_type === 'Employee Salary'" class="flex justify-between text-orange-600"><span>+ Employer SOCSO</span><span>RM {{ result.socso_employer.toFixed(2) }}</span></div>
                        <div v-if="form.payslip_type === 'Employee Salary'" class="flex justify-between text-orange-600"><span>+ Employer EIS</span><span>RM {{ result.eis_employer.toFixed(2) }}</span></div>
                        <div class="flex justify-between font-bold text-lg border-t pt-2">
                            <span>Total Cost to Employer</span>
                            <span class="text-orange-600">RM {{ result.employer_cost.toFixed(2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="bg-blue-50 rounded-lg p-4 text-xs text-blue-800">
                    <h4 class="font-bold mb-2">Important Notes:</h4>
                    <ul class="list-disc list-inside space-y-1">
                        <li>EPF rates: Malaysian below 60 (11%/13%), Malaysian 60+ (0%/4%), Non-Malaysian (2%/2%)</li>
                        <li>SOCSO max wage: RM6,000. Age 60+: Category 2 (Injury Only) mandatory</li>
                        <li>EIS: Not applicable for Non-Malaysian workers and those aged 60+</li>
                        <li>PCB: Non-residents taxed at flat 30%, residents use progressive rates</li>
                        <li>Director Fee & Intern Wages: No EPF/SOCSO/EIS contributions</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-6 text-center text-xs text-gray-500">
            <p>This calculator provides estimates based on Malaysian statutory rates for 2026.</p>
            <p>For official calculations, please consult LHDN, KWSP, PERKESO, and PSMB.</p>
            <p class="mt-2">&copy; <?= date('Y') ?> <?= COMPANY_NAME ?></p>
        </div>
    </div>
</div>

<script>
const taxCategories = <?= json_encode($TAX_CATEGORIES) ?>;

Vue.createApp({
    data() {
        return {
            taxCategories,
            form: {
                payslip_type: 'Employee Salary',
                nationality: 'Malaysian',
                age: 30,
                working_days: 26,
                tax_resident: 'Yes',
                tax_category: 0,
                socso_category: 'Category 1 (Injury + Invalidity)',
                basic_salary: 3000,
                allowances: [],
                overtime: [],
                deductions: [],
                unpaid_leaves: []
            },
            result: {
                gross_salary: 0,
                total_allowances: 0,
                total_overtime: 0,
                total_deductions: 0,
                total_unpaid_leave: 0,
                total_other_deductions: 0,
                epf_employee: 0,
                epf_employee_rate: 0,
                epf_employer: 0,
                epf_employer_rate: 0,
                socso_employee: 0,
                socso_employer: 0,
                eis_employee: 0,
                eis_employer: 0,
                pcb_tax: 0,
                total_statutory_employee: 0,
                total_statutory_employer: 0,
                net_salary: 0,
                employer_cost: 0
            }
        };
    },
    mounted() {
        this.calculate();
    },
    methods: {
        onAgeChange() {
            if (this.form.age >= 60) {
                this.form.socso_category = 'Category 2 (Injury Only)';
            }
            this.calculate();
        },
        getOTRate() {
            return this.form.basic_salary / this.form.working_days / 8;
        },
        getLeaveRate() {
            return this.form.basic_salary / this.form.working_days;
        },
        addOT() {
            const rate = this.getOTRate();
            this.form.overtime.push({ hours: 1, multiplier: '1.5', rate: parseFloat(rate.toFixed(2)), amount: parseFloat((rate * 1.5).toFixed(2)) });
            this.calculate();
        },
        calcOT(i) {
            const o = this.form.overtime[i];
            o.amount = parseFloat(((o.hours || 0) * (o.rate || 0) * parseFloat(o.multiplier || 1.5)).toFixed(2));
            this.calculate();
        },
        addLeave() {
            const rate = this.getLeaveRate();
            this.form.unpaid_leaves.push({ days: 1, amount: parseFloat(rate.toFixed(2)) });
            this.calculate();
        },
        calcLeave(i) {
            const u = this.form.unpaid_leaves[i];
            u.amount = parseFloat(((u.days || 0) * this.getLeaveRate()).toFixed(2));
            this.calculate();
        },
        async calculate() {
            // Calculate totals
            this.result.total_allowances = this.form.allowances.reduce((s, a) => s + (parseFloat(a.amount) || 0), 0);
            this.result.total_overtime = this.form.overtime.reduce((s, o) => s + (parseFloat(o.amount) || 0), 0);
            this.result.total_deductions = this.form.deductions.reduce((s, d) => s + (parseFloat(d.amount) || 0), 0);
            this.result.total_unpaid_leave = this.form.unpaid_leaves.reduce((s, u) => s + (parseFloat(u.amount) || 0), 0);
            this.result.total_other_deductions = this.result.total_deductions + this.result.total_unpaid_leave;
            
            // Gross salary
            this.result.gross_salary = (parseFloat(this.form.basic_salary) || 0) + this.result.total_allowances + this.result.total_overtime;
            
            // For Director Fee and Intern Wages, no statutory contributions (except PCB for Director)
            if (this.form.payslip_type === 'Director Fee' || this.form.payslip_type === 'Intern Wages') {
                this.result.epf_employee = 0;
                this.result.epf_employee_rate = 0;
                this.result.epf_employer = 0;
                this.result.epf_employer_rate = 0;
                this.result.socso_employee = 0;
                this.result.socso_employer = 0;
                this.result.eis_employee = 0;
                this.result.eis_employer = 0;
                this.result.total_statutory_employee = 0;
                this.result.total_statutory_employer = 0;
            }
            
            // Call server for statutory calculations
            if (this.result.gross_salary > 0) {
                try {
                    const fd = new FormData();
                    fd.append('action', 'calculate_statutory_preview');
                    fd.append('gross_salary', this.result.gross_salary);
                    fd.append('nationality', this.form.nationality);
                    fd.append('age', this.form.age || 0);
                    fd.append('socso_category', this.form.socso_category);
                    fd.append('tax_resident', this.form.tax_resident);
                    fd.append('tax_category', this.form.tax_category);
                    fd.append('payslip_type', this.form.payslip_type);
                    
                    const response = await axios.post('', fd);
                    const r = response.data;
                    
                    if (r.success) {
                        if (this.form.payslip_type === 'Employee Salary') {
                            this.result.epf_employee = r.epf.employee_amount;
                            this.result.epf_employee_rate = r.epf.employee_rate;
                            this.result.epf_employer = r.epf.employer_amount;
                            this.result.epf_employer_rate = r.epf.employer_rate;
                            this.result.socso_employee = r.socso.employee;
                            this.result.socso_employer = r.socso.employer;
                            this.result.eis_employee = r.eis.employee;
                            this.result.eis_employer = r.eis.employer;
                            this.result.total_statutory_employee = r.epf.employee_amount + r.socso.employee + r.eis.employee;
                            this.result.total_statutory_employer = r.epf.employer_amount + r.socso.employer + r.eis.employer;
                        }
                        this.result.pcb_tax = r.pcb;
                    }
                } catch (e) {
                    console.error('Calculation error:', e);
                }
            } else {
                // Reset ALL statutory values when gross is 0 or less
                this.result.pcb_tax = 0;
                this.result.epf_employee = 0;
                this.result.epf_employee_rate = 0;
                this.result.epf_employer = 0;
                this.result.epf_employer_rate = 0;
                this.result.socso_employee = 0;
                this.result.socso_employer = 0;
                this.result.eis_employee = 0;
                this.result.eis_employer = 0;
                this.result.total_statutory_employee = 0;
                this.result.total_statutory_employer = 0;
            }
            
            // Calculate net salary
            this.result.net_salary = this.result.gross_salary 
                - this.result.total_statutory_employee 
                - this.result.pcb_tax 
                - this.result.total_other_deductions;
            
            // Calculate employer cost
            this.result.employer_cost = this.result.gross_salary + this.result.total_statutory_employer;
        }
    }
}).mount('#calculator');
</script>
</body>
</html>
# Malaysia Payroll Calculator 2026

A free, open-source payroll calculator for Malaysian employers and employees. Calculate all statutory contributions including PCB (Monthly Tax Deduction), EPF (Employees Provident Fund), SOCSO (Social Security Organization), and EIS (Employment Insurance System) with accurate 2026 rates.

**Demo:** [Live Calculator](https://vyrox.com/miniapps/payroll88/payroll-calculator.php)
**Demo:** [Live Calculator](https://vyrox.com/miniapps/payroll88/payroll-calculator.php)
**Demo:** [Live Calculator](https://vyrox.com/miniapps/payroll88/payroll-calculator.php)

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![Vue.js](https://img.shields.io/badge/Vue.js-3.x-green.svg)

## Features

### Statutory Calculations
- **EPF/KWSP** - Employee Provident Fund contributions with bracket-based lookup tables
  - Malaysian below 60: 11% employee / 13% employer (salary ≤ RM5,000) or 12% employer (salary > RM5,000)
  - Malaysian 60+: 0% employee / 4% employer
  - Non-Malaysian: 2% employee / 2% employer
  
- **SOCSO/PERKESO** - Social Security Organization contributions
  - Category 1 (Injury + Invalidity): Both employer and employee contribute
  - Category 2 (Injury Only): Employer only, mandatory for age 60+
  - Maximum insurable wage: RM6,000
  
- **EIS/SIP** - Employment Insurance System contributions
  - Equal employer and employee contributions
  - Exempt for Non-Malaysian workers
  - Exempt for age 60 and above
  - Maximum insurable wage: RM6,000
  
- **PCB/MTD** - Monthly Tax Deduction (Potongan Cukai Bulanan)
  - Progressive tax rates for residents (0% - 30%)
  - Flat 30% rate for non-residents
  - Automatic relief calculations (individual, spouse, children, EPF)
  - Tax rebate for low income earners

### Payslip Types Supported
| Type | EPF | SOCSO | EIS | PCB |
|------|-----|-------|-----|-----|
| Employee Salary | Yes | Yes | Yes | Yes |
| Director Fee | No | No | No | Yes |
| Intern Wages | No | No | No | No |

### Additional Features
- Real-time calculation updates
- Dynamic allowances (unlimited)
- Dynamic overtime with configurable multipliers (1.5x, 2x, 3x)
- Dynamic unpaid leave deductions
- Dynamic other deductions
- Net salary calculation
- Total employer cost calculation
- 23 tax categories (Single, Spouse working/not working, 0-10 children)
- Mobile-responsive design
- SEO optimized

## Requirements

- PHP 7.4 or higher
- Web server (Apache, Nginx, etc.)
- Modern web browser with JavaScript enabled

## Installation

1. **Download/Clone the repository**
```bash
   git clone https://github.com/yourusername/malaysia-payroll-calculator.git
   cd malaysia-payroll-calculator
```

2. **Place files in your web server directory**
```
   /your-web-root/
   ├── payroll-calculator.php
   ├── epf.json
   ├── socso.json
   ├── eis.json
   └── pcb.json
```

3. **Access the calculator**
```
   http://yourdomain.com/payroll-calculator.php
```

## File Structure
```
malaysia-payroll-calculator/
├── payroll-calculator.php   # Main application (PHP backend + Vue.js frontend)
├── epf.json                 # EPF contribution brackets and rates
├── socso.json               # SOCSO contribution brackets
├── eis.json                 # EIS contribution brackets
├── pcb.json                 # PCB tax brackets and relief amounts
└── README.md                # This file
```

## JSON Configuration Files

### epf.json
Contains EPF contribution tables for different categories:
- `part_a` - Malaysian employees below 60 years
- `part_e` - Malaysian employees 60 years and above
- `foreign_worker` - Non-Malaysian employees
- Bracket-based lookup for salaries up to RM20,000
- Percentage-based calculation for salaries above RM20,000

### socso.json
Contains SOCSO contribution tables:
- `category_1` - Employment Injury & Invalidity schemes
- `category_2` - Employment Injury scheme only
- Maximum insurable wage configuration
- Bracket-based contribution lookup

### eis.json
Contains EIS contribution tables:
- Bracket-based contribution lookup
- Maximum age limit (60)
- Maximum insurable wage (RM6,000)
- Foreign worker exemption flag

### pcb.json
Contains PCB/income tax configuration:
- `tax_brackets` - Progressive tax rate brackets
- `reliefs` - Personal relief amounts (individual, spouse, children)
- `epf_max_relief` - Maximum EPF relief amount
- `non_resident_rate` - Flat tax rate for non-residents

## Usage

### Basic Usage
1. Select **Payslip Type** (Employee Salary, Director Fee, or Intern Wages)
2. Select **Nationality** (Malaysian or Non-Malaysian)
3. Enter **Age** (affects EPF rates, SOCSO category, EIS eligibility)
4. Configure **Tax Settings** (Tax Resident status and Tax Category)
5. Select **SOCSO Category** (auto-locked to Category 2 for age 60+)
6. Enter **Basic Salary**
7. Add any **Allowances**, **Overtime**, or **Deductions** as needed
8. View real-time calculation results

### Understanding Results

**Net Salary Calculation:**
```
Net Salary = Gross Salary 
           - Employee EPF 
           - Employee SOCSO 
           - Employee EIS 
           - PCB Tax 
           - Unpaid Leave 
           - Other Deductions
```

**Employer Cost Calculation:**
```
Employer Cost = Gross Salary 
              + Employer EPF 
              + Employer SOCSO 
              + Employer EIS
```

## API Endpoint

The calculator exposes a POST endpoint for programmatic access:

**Endpoint:** `POST /payroll-calculator.php`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| action | string | Yes | Must be `calculate_statutory_preview` |
| gross_salary | float | Yes | Gross salary amount |
| nationality | string | No | `Malaysian` or `Non-Malaysian` (default: Malaysian) |
| age | int | No | Employee age in years (default: 0) |
| socso_category | string | No | SOCSO category (default: Category 1) |
| tax_resident | string | No | `Yes` or `No` (default: Yes) |
| tax_category | int | No | Tax category 0-22 (default: 0) |
| payslip_type | string | No | `Employee Salary`, `Director Fee`, or `Intern Wages` |

**Response:**
```json
{
  "success": true,
  "epf": {
    "employee_rate": 11,
    "employee_amount": 330,
    "employer_rate": 13,
    "employer_amount": 390
  },
  "socso": {
    "employer": 59.85,
    "employee": 17.05
  },
  "eis": {
    "employer": 5.90,
    "employee": 5.90
  },
  "pcb": 0
}
```

**Example cURL Request:**
```bash
curl -X POST http://yourdomain.com/payroll-calculator.php \
  -d "action=calculate_statutory_preview" \
  -d "gross_salary=3000" \
  -d "nationality=Malaysian" \
  -d "age=30" \
  -d "socso_category=Category 1 (Injury + Invalidity)" \
  -d "tax_resident=Yes" \
  -d "tax_category=0" \
  -d "payslip_type=Employee Salary"
```

## Tax Categories Reference

| Code | Description |
|------|-------------|
| 0 | Single |
| 1 | Spouse not working, No child |
| 2 | Spouse not working, 1 child |
| 3 | Spouse not working, 2 children |
| ... | ... |
| 11 | Spouse not working, 10 children |
| 12 | Spouse working, No child |
| 13 | Spouse working, 1 child |
| ... | ... |
| 22 | Spouse working, 10 children |

## Statutory Rates Summary (2026)

### EPF Rates
| Category | Employee | Employer |
|----------|----------|----------|
| Malaysian < 60, Salary ≤ RM5,000 | 11% | 13% |
| Malaysian < 60, Salary > RM5,000 | 11% | 12% |
| Malaysian ≥ 60 | 0% | 4% |
| Non-Malaysian | 2% | 2% |

### SOCSO Maximum Contributions (Salary ≥ RM6,000)
| Category | Employer | Employee |
|----------|----------|----------|
| Category 1 | RM104.15 | RM29.75 |
| Category 2 | RM74.40 | RM0.00 |

### EIS Maximum Contributions (Salary ≥ RM6,000)
| | Employer | Employee |
|--|----------|----------|
| Contribution | RM11.90 | RM11.90 |

### PCB Tax Brackets (Residents)
| Chargeable Income (Annual) | Rate |
|---------------------------|------|
| RM0 - RM5,000 | 0% |
| RM5,001 - RM20,000 | 1% |
| RM20,001 - RM35,000 | 3% |
| RM35,001 - RM50,000 | 6% |
| RM50,001 - RM70,000 | 11% |
| RM70,001 - RM100,000 | 19% |
| RM100,001 - RM400,000 | 25% |
| RM400,001 - RM600,000 | 26% |
| RM600,001 - RM2,000,000 | 28% |
| Above RM2,000,000 | 30% |

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome for Android)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## Disclaimer

This calculator provides estimates based on publicly available Malaysian statutory rates for 2026. While we strive for accuracy, this tool should be used for reference purposes only. For official calculations and compliance, please consult:

- **LHDN** (Lembaga Hasil Dalam Negeri) - Income Tax
- **KWSP/EPF** (Kumpulan Wang Simpanan Pekerja) - Provident Fund
- **PERKESO/SOCSO** (Pertubuhan Keselamatan Sosial) - Social Security
- **PSMB/HRDF** (Pembangunan Sumber Manusia Berhad) - Employment Insurance

The developers are not responsible for any discrepancies or errors in calculations. Always verify with official sources for payroll processing.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- Malaysian statutory contribution tables from official government sources
- [Vue.js](https://vuejs.org/) - Frontend framework
- [Tailwind CSS](https://tailwindcss.com/) - CSS framework
- [Axios](https://axios-http.com/) - HTTP client

## Support

If you find this calculator helpful, please consider:
- Giving it a star on GitHub
- Sharing it with others who might benefit
- Reporting any bugs or issues
- Contributing improvements

## Contact

For questions, suggestions, or support, please open an issue on GitHub.

---

**Made with care for Malaysian businesses and employees**

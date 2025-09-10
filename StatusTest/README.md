# Status Inconsistency Tool

A PHP-based web form that calculates the **status inconsistency** between a person's self-assessment and their socioeconomic status based on education, income, and occupation.

## What the Scripts Do

### Main Script: `test-status-inconsistency.php`

This is a self-contained PHP web application that:

#### **Data Loading & Processing**
- Loads **occupation titles** from `data/occupations.csv` (ISCO codes → profession names)
- Loads **ISEI scores** from `data/isei08.csv` (ISCO codes → International Socio-Economic Index values)
- Merges both datasets by matching ISCO occupation codes
- Provides fallback data if external files are missing

#### **User Interface**
- Presents a web form with four input sections:
  1. **Education Level** (7 options: from primary to doctoral)
  2. **Income Quintile** (5 options: from bottom 25% to top 10%)
  3. **Occupation** (dropdown populated from merged CSV data)
  4. **Status** (1-10 slider for self-assessment)

#### **Calculations**
- **Normalizes** all inputs to a 1-10 scale:
  - Education: Linear mapping across 7 levels
  - Income: Linear mapping across 5 quintiles  
  - Occupation: ISEI scores normalized to 1-10 range
- **Calculates status** as the average of normalized education, income, and occupation scores
- **Computes status inconsistency** as the difference between self-assessment and calculated status
- **Classifies inconsistency level**: Low (≤1), Moderate (1-2), High (>2)

#### **Output & Results**
- Displays a detailed results table showing:
  - Raw and normalized values for each component
  - Final calculated vs self-assessed status scores
  - Status inconsistency magnitude and classification
- Provides error handling for missing data or invalid inputs

### Alternative Script: `test-status-inconsistency - от фанаря.php`

A simplified version with:
- **Hardcoded profession data** (no external file dependencies)
- **Same calculation logic** and user interface
- **Guaranteed functionality** regardless of data file availability
- Useful for testing or standalone deployment

## Data Requirements

### Required Files (for main script)

1. **`data/isei08.csv`** - ISCO to ISEI mapping
   - Columns: ISCO codes, ISEI values
   - Format: `isco08_tempvar,isei08_tempvar` or similar

2. **`data/occupations.csv`** - ISCO to profession names
   - Columns: `Code,Label`
   - Format: 4-digit ISCO codes with full profession titles

## Use Cases

- **Sociological research** - measuring status consistency in populations
- **Status perception analysis** - individual status perception analysis  
- **Survey applications** - integrating standardized occupation and status measures
- **Educational purposes** - demonstrating socioeconomic status concepts

## Technical Features

- **Robust CSV parsing** with automatic delimiter detection
- **Flexible data format support** for various CSV structures
- **Comprehensive error handling** and validation
- **Responsive web interface** with clear user feedback
- **No external dependencies** beyond standard PHP functions

## Output Interpretation

- **Positive inconsistency**: Person rates themselves higher than calculated measures suggest
- **Negative inconsistency**: Person rates themselves lower than calculated measures suggest  
- **Low inconsistency**: Good alignment between perception and calculated status
- **High inconsistency**: Gap between self-perception and calculated status

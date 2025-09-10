# Status Inconsistency Tool - To Do List

## Current Problems Identified

### 1. Data Quality Issues in `occupations.csv`
- [ ] **Remove group headers** - File contains non-profession entries like:
  - `1,Managers`
  - `2,Professionals` 
  - `21,Science and Engineering`
- [ ] **Fix truncated profession labels** - Many titles are cut off:
  - `1439,Services Managers Not` (incomplete)
  - `1113,Traditional Chiefs and` (missing end)
  - `2132,Farming, Forestry and` (missing end)
- [ ] **Keep only 4-digit ISCO codes** - Remove 1-3 digit group codes
- [ ] **Ensure complete profession titles** in the Label column

### 2. ISEI Data Integration
- [ ] **Verify `isei08.csv` exists** and is readable
- [ ] **Check ISEI file format** - should have columns like:
  - `isco08_tempvar` or `isco08` (for codes)
  - `isei08_tempvar` or `isei08` (for ISEI values)
- [ ] **Ensure ISCO codes match** between both CSV files for proper merging
- [ ] **Validate ISEI numeric values** are present and valid

### 3. CSV File Structure
- [ ] **Clean `occupations.csv` format**:
  ```csv
  Code,Label
  1112,Senior Government Officials
  1211,Finance Managers
  2310,University and Higher Education Teachers
  ```
- [ ] **Remove empty rows and placeholder text**
- [ ] **Ensure UTF-8 encoding without BOM**

### 4. Testing & Validation
- [ ] **Test with sample data** - verify profession dropdown populates correctly
- [ ] **Check ISEI score display** - ensure values show instead of "—"
- [ ] **Validate calculation logic** - test status inconsistency formula
- [ ] **Test error handling** for missing files

## Expected Outcome
Once fixed, the PHP script should:
- Load profession titles from `occupations.csv`
- Load ISEI scores from `isei08.csv`
- Merge data by matching ISCO codes
- Display professions with format: "Title [ISCO code] (ISEI score)"
- Calculate objective status index from education, income, and ISEI
- Compare with subjective self-assessment to determine status inconsistency

## Priority
**High** - The tool cannot function properly without clean, properly formatted data files.

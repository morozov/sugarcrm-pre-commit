SugarCRM Code Standards Validator
=================================

This tool may be used as a `pre-commit` hook for development of projects
with lots of legacy code. It runs `PHP_CodeSniffer` validation before commit
and dispays errors and warnings only in changed lines.

Installation
=================================

1. Install `PHP_CodeSniffer` from PEAR.
2. Clone git@github.com:morozov/sugarcrm-pre-commit.git to your computer.
3. In `bin/pre-commit` file specify desired coding standard.
4. Create symlink from validator binary to your project repository
   ```
   ln -s /path/to/sugarcrm-pre-commit/pre-commit /path/to/repo/.git/hooks/
   ```

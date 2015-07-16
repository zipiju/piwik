#!/usr/bin/env bash
if [ "$SKIP_INSTALL_PYTHON_26" == "1" ]; then
    echo "Skipping Python 2.6 installation."
    exit 0;
fi

echo "Installed via .travis.yml."

# Log Analytics works with Python 2.6 or 2.7 but we want to test on 2.6
echo "python2.6 --version:"
python2.6 --version

echo ""

echo "python --version:"
python --version

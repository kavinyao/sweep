#!/bin/bash

TEMPLATE=template.html
CONFIG_FILE=config.php

TEMP_OUTPUT=template.php
TEMP_USE=final.php
FINAL=output.html

php ../sweep.php $TEMPLATE > $TEMP_OUTPUT
cp $CONFIG_FILE $TEMP_USE
echo "include('../sweep.extra.php');" >> $TEMP_USE
echo "include('$TEMP_OUTPUT');" >> $TEMP_USE
php $TEMP_USE > $FINAL
rm $TEMP_OUTPUT $TEMP_USE

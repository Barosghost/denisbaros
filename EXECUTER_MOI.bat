@echo off
echo ========================================
echo   AJOUT DES COLONNES MANQUANTES
echo ========================================
echo.

cd /d c:\wamp64\www\denis

echo Execution du script de correction...
php fix_columns_now.php

echo.
echo ========================================
echo   TERMINE !
echo ========================================
echo.
echo Maintenant, rechargez vos pages dans le navigateur avec Ctrl+F5
echo.
pause

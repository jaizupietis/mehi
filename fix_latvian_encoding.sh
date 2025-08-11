
#!/bin/bash

# Skripts latviešu burtu problēmas novēršanai uz LAMP servera
# Palaidiet ar: bash fix_latvian_encoding.sh

echo "Sāku latviešu burtu kodējuma labošanu..."

# 1. Backup datubāzes
echo "Veidoju datubāzes backup..."
mysqldump -u tasks -pAstalavista1920 mehu_uzd > backup_before_encoding_fix_$(date +%Y%m%d_%H%M%S).sql

# 2. Palaiž kodējuma labojumus
echo "Laboju datubāzes kodējumu..."
mysql -u tasks -pAstalavista1920 mehu_uzd < fix_encoding.sql

# 3. Pārbauda rezultātu
echo "Pārbaudu rezultātu..."
mysql -u tasks -pAstalavista1920 mehu_uzd -e "SELECT CONCAT(vards, ' ', uzvards) as pilns_vards FROM lietotaji WHERE loma = 'Mehāniķis' LIMIT 3;"

echo "Kodējuma labošana pabeigta!"
echo "Tagad CSV eksports parādīs latviešu burtus pareizi."

# 4. Restart Apache (ja nepieciešams)
echo "Restartēju Apache servisu..."
sudo systemctl restart apache2

echo "Viss gatavs! Pārbaudiet CSV eksportu."

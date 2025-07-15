# AVOTI Task Management System

Uzdevumu pārvaldības sistēma SIA "AVOTI" kokapstrādes un mēbeļu ražošanas uzņēmumam.

## Sistēmas apraksts

Task Management sistēma ir izveidota, lai pārvaldītu uzdevumus un problēmas uzņēmumā. Sistēmā ir 4 lietotāju lomas:

- **Administrators** - pārvalda lietotājus, konfigurāciju, skatās atskaites
- **Menedžeris** - izveido uzdevumus, pārvalda problēmas
- **Operators** - ziņo problēmas par iekārtām
- **Mehāniķis** - izpilda piešķirtos uzdevumus

## Sistēmas prasības

### Servera prasības
- **OS**: Linux Debian 12 (vai jebkura LAMP atbalstoša sistēma)
- **Web serveris**: Apache 2.4+
- **PHP**: 7.4+ (ieteicams 8.0+)
- **Datubāze**: MariaDB 10.3+ vai MySQL 8.0+
- **RAM**: Minimums 512MB, ieteicams 1GB+
- **Diska vieta**: Minimums 500MB

### PHP moduļi
- `php-mysql` (PDO MySQL atbalsts)
- `php-gd` (attēlu apstrāde)
- `php-mbstring` (UTF-8 atbalsts)
- `php-xml` (XML/HTML apstrāde)
- `php-curl` (HTTP pieprasījumi)
- `php-zip` (ZIP arhīvu atbalsts)
- `php-intl` (starptautiskošana)

## Instalācijas process

### 1. Servera sagatavošana

```bash
# Atjaunināt sistēmu
sudo apt update && sudo apt upgrade -y

# Instalēt LAMP stack
sudo apt install apache2 mariadb-server php php-mysql php-gd php-mbstring php-xml php-curl php-zip php-intl -y

# Aktivēt Apache moduļus
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod expires
sudo a2enmod deflate

# Restartēt Apache
sudo systemctl restart apache2
```

### 2. MariaDB konfigurācija

```bash
# Palaist MariaDB drošības konfigurāciju
sudo mysql_secure_installation

# Pieslēgties MariaDB
sudo mysql -u root -p

# Izveidot datubāzi un lietotāju
CREATE DATABASE mehu_uzd CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;
CREATE USER 'tasks'@'localhost' IDENTIFIED BY 'Astalavista1920';
GRANT ALL PRIVILEGES ON mehu_uzd.* TO 'tasks'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Projekta failu ielāde

```bash
# Izveidot projekta direktoriju
sudo mkdir -p /var/www/mehi

# Mainīt īpašnieku
sudo chown $USER:$USER /var/www/mehi

# Pāriet uz projekta direktoriju
cd /var/www/mehi

# Lejupielādēt visus sistēmas failus (no repositorija vai kopēt manuāli)
# Strukturētā veidā izvietot šos failus:
```

#### Failu struktūra:
```
/var/www/mehi/
├── index.php                    # Sākuma lapa
├── login.php                    # Pieslēgšanās
├── logout.php                   # Izrakstīšanās
├── config.php                   # Konfigurācija
├── profile.php                  # Lietotāja profils
├── unauthorized.php             # Piekļuves liegta
├── 404.php                      # 404 kļūda
├── 500.php                      # 500 kļūda
├── tasks.php                    # Uzdevumu saraksts
├── create_task.php              # Uzdevuma izveidošana
├── my_tasks.php                 # Mehāniķa uzdevumi
├── problems.php                 # Problēmu saraksts
├── report_problem.php           # Problēmas ziņošana
├── my_problems.php              # Operatora problēmas
├── users.php                    # Lietotāju pārvaldība
├── settings.php                 # Iestatījumi
├── reports.php                  # Atskaites
├── notifications.php            # Paziņojumi
├── .htaccess                    # Apache konfigurācija
├── database.sql                 # DB struktūra
├── includes/
│   ├── header.php              # Galvenes template
│   └── footer.php              # Kājenes template
├── ajax/
│   ├── get_task_details.php    # Uzdevuma detaļas
│   ├── get_problem_details.php # Problēmas detaļas
│   ├── get_related_tasks.php   # Saistītie uzdevumi
│   └── get_notification_count.php # Paziņojumu skaits
├── assets/
│   └── css/
│       └── style.css           # Galvenais CSS
└── uploads/                     # Augšupielādēto failu direktorijs
```

### 4. Datubāzes inicializācija

```bash
# Importēt datubāzes struktūru
mysql -u tasks -p mehu_uzd < database.sql
```

### 5. Failu atļauju konfigurācija

```bash
# Iestatīt pareizās atļaujas
sudo chown -R www-data:www-data /var/www/mehi/
sudo chmod -R 755 /var/www/mehi/
sudo chmod -R 777 /var/www/mehi/uploads/

# Aizsargāt sensitīvos failus
sudo chmod 600 /var/www/mehi/config.php
sudo chmod 600 /var/www/mehi/database.sql
```

### 6. Apache Virtual Host konfigurācija

Izveidot failu `/etc/apache2/sites-available/mehi.conf`:

```apache
<VirtualHost *:80>
    ServerName 192.168.2.11
    DocumentRoot /var/www/mehi
    
    <Directory /var/www/mehi>
        AllowOverride All
        Require ip 192.168.2
        Require ip 127.0.0.1
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/mehi_error.log
    CustomLog ${APACHE_LOG_DIR}/mehi_access.log combined
    
    # PHP konfigurācija
    php_admin_value upload_max_filesize 10M
    php_admin_value post_max_size 10M
    php_admin_value max_execution_time 60
    php_admin_value memory_limit 128M
    
    # Drošības iestatījumi
    php_admin_value expose_php Off
    php_admin_value display_errors Off
    php_admin_value log_errors On
    php_admin_value error_log /var/log/apache2/mehi_php_errors.log
</VirtualHost>
```

```bash
# Aktivēt vietni
sudo a2ensite mehi.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2
```

### 7. PHP konfigurācijas pielāgošana

Rediģēt `/etc/php/8.1/apache2/php.ini` (versija var atšķirties):

```ini
# Failu augšupielādes iestatījumi
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20

# Sesiju iestatījumi
session.cookie_httponly = 1
session.cookie_secure = 0
session.use_strict_mode = 1
session.cookie_samesite = "Strict"

# Laika zona
date.timezone = Europe/Riga

# Atmiņas limits
memory_limit = 128M

# Kļūdu ziņošana (ražošanas vidē)
display_errors = Off
log_errors = On
error_log = /var/log/apache2/mehi_php_errors.log
```

```bash
# Restartēt Apache pēc izmaiņām
sudo systemctl restart apache2
```

### 8. Sistēmas pārbaude

1. Atvērt pārlūkprogrammā: `http://192.168.2.11/mehi`
2. Pieslēgties ar noklusēto administratora kontu:
   - **Lietotājvārds**: `admin`
   - **Parole**: `password` (mainīt pēc pirmās pieslēgšanās!)

### 9. Sākotnējā konfigurācija

1. **Mainīt administratora paroli**:
   - Doties uz Profils → Mainīt paroli
   
2. **Pievienot lietotājus**:
   - Doties uz Lietotāji → Pievienot lietotāju
   - Izveidot lietotājus visām 4 lomām
   
3. **Konfigurēt vietas un iekārtas**:
   - Doties uz Iestatījumi
   - Pievienot uzņēmuma vietas un iekārtas
   
4. **Pārbaudīt paziņojumu sistēmu**:
   - Izveidot testa uzdevumu
   - Pārbaudīt vai paziņojumi tiek nosūtīti

## Drošības ieteikumi

### 1. Paroles drošība
```bash
# Mainīt datubāzes lietotāja paroli
mysql -u root -p
ALTER USER 'tasks'@'localhost' IDENTIFIED BY 'JAUNA_SPĒCĪGA_PAROLE';
FLUSH PRIVILEGES;
```

### 2. Failu sistēmas drošība
```bash
# Ierobežot piekļuvi konfigurācijas failiem
sudo chmod 600 /var/www/mehi/config.php
sudo chown www-data:www-data /var/www/mehi/config.php

# Novērst .git direktorija piekļuvi
echo "RedirectMatch 404 /\.git" | sudo tee -a /var/www/mehi/.htaccess
```

### 3. Firewall konfigurācija
```bash
# UFW firewall (ja izmanto)
sudo ufw allow 22    # SSH
sudo ufw allow 80    # HTTP
sudo ufw allow 443   # HTTPS (ja nepieciešams)
sudo ufw enable
```

### 4. SSL sertifikāta uzstādīšana (ieteicams)
```bash
# Let's Encrypt (ja domēns ir pieejams)
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d jūsu-domēns.lv
```

## Sistēmas uzturēšana

### Regulārā dublēšana
```bash
#!/bin/bash
# Izveidot backup skriptu /home/backup_mehi.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/home/backups"
mkdir -p $BACKUP_DIR

# Datubāzes backup
mysqldump -u tasks -pAstalavista1920 mehu_uzd > $BACKUP_DIR/mehi_db_$DATE.sql

# Failu backup
tar -czf $BACKUP_DIR/mehi_files_$DATE.tar.gz /var/www/mehi --exclude=/var/www/mehi/uploads/temp

# Dzēst vecākus backup failus (30 dienas)
find $BACKUP_DIR -name "mehi_*" -mtime +30 -delete

echo "Backup completed: $DATE"
```

```bash
# Pievienot crontab automātiskai dublēšanai
sudo crontab -e
# Pievienot līniju (katru dienu 02:00):
0 2 * * * /home/backup_mehi.sh >> /var/log/mehi_backup.log 2>&1
```

### Log failu uzraudzība
```bash
# Pārbaudīt kļūdu logus
sudo tail -f /var/log/apache2/mehi_error.log
sudo tail -f /var/log/apache2/mehi_php_errors.log

# Log rotācija
sudo nano /etc/logrotate.d/mehi
```

Pievienot logrotate konfigurāciju:
```
/var/log/apache2/mehi_*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 root root
    postrotate
        systemctl reload apache2
    endscript
}
```

## Problēmu risināšana

### Biežākās problēmas

1. **"Permission denied" kļūdas**:
   ```bash
   sudo chown -R www-data:www-data /var/www/mehi/
   sudo chmod -R 755 /var/www/mehi/
   sudo chmod 777 /var/www/mehi/uploads/
   ```

2. **Datubāzes pieslēgšanās kļūdas**:
   - Pārbaudīt config.php iestatījumus
   - Pārbaudīt MariaDB statusu: `sudo systemctl status mariadb`

3. **500 Internal Server Error**:
   - Pārbaudīt Apache error log: `sudo tail -f /var/log/apache2/error.log`
   - Pārbaudīt PHP sintaksi: `php -l config.php`

4. **Failu augšupielādes problēmas**:
   - Pārbaudīt uploads/ direktorija atļaujas
   - Pārbaudīt PHP upload iestatījumus

### Kontakti

- **Tehniskais atbalsts**: support@avoti.lv
- **Sistēmas administrators**: admin@avoti.lv
- **Tālrunis**: +371 1234-5678

### Licences informācija

Sistēma ir izveidota specifiski SIA "AVOTI" vajadzībām. Visas tiesības aizsargātas.

**Versija**: 1.0  
**Pēdējā atjaunošana**: 2025-01-15
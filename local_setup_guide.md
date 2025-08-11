
# AVOTI TMS Lokālā Tīkla Uzstādīšana

## 1. Sagatavošana

### Noskaidrojiet savu lokālo IP adresi:

**Windows:**
```cmd
ipconfig
```
Meklējiet "IPv4 Address" zem jūsu WiFi adaptera.

**Linux/Mac:**
```bash
hostname -I
# vai
ifconfig | grep "inet "
```

**Piemērs:** 192.168.1.150

## 2. Atjauniniet Capacitor konfigurāciju

Failā `capacitor.config.ts` aizstājiet:
```typescript
url: 'http://192.168.1.XXX/mehi'
```

Ar savu IP adresi, piemēram:
```typescript
url: 'http://192.168.1.150/mehi'
```

## 3. LAMP servera konfigurācija

### Apache konfigurācija
Pārliecinieties, ka Apache klausās visas saskarnes:

Failā `/etc/apache2/apache2.conf` vai `/etc/httpd/conf/httpd.conf`:
```apache
Listen 0.0.0.0:80
```

### Ugunsmūra iestatījumi
**Windows (Windows Firewall):**
- Atveriet Windows Defender Firewall
- Allow an app → Apache HTTP Server

**Linux (ufw):**
```bash
sudo ufw allow 80
sudo ufw allow from 192.168.1.0/24
```

## 4. Testēšana

Pārbaudiet, vai serveris ir pieejams no citās ierīces:
```bash
# No citas ierīces tīklā
ping 192.168.1.150
curl http://192.168.1.150/mehi
```

## 5. APK izveide

Kad konfigurācija ir pareiza:
```bash
./build_android.sh
```

## 6. APK instalācija uz telefoniem

1. Pārsūtiet `avoti-tms-debug.apk` uz Android ierīci
2. Iespējojiet "Nezināmi avoti" ierīces iestatījumos
3. Uzstādiet APK
4. Pārliecinieties, ka telefons ir savienots ar to pašu WiFi tīklu

## Problēmu risināšana

### "Serveru nevar sasniegt"
- Pārbaudiet IP adresi `capacitor.config.ts`
- Pārbaudiet vai ugunsmūris neblokē savienojumu
- Testējiet serveris ar pārlūku no cita datora

### "CORS kļūdas"
Pievienojiet jūsu PHP projektā:
```php
// config.php vai .htaccess
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
```

## Drošības apsvērumi

- Lokālais tīkls ir paredzēts tikai iekšējai izmantošanai
- APK fāls nav parakstīts ar production sertifikātu
- Dati netiek šifrēti HTTP savienojumā

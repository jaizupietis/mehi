
# AVOTI TMS Android APK Izveides Instrukcijas

## Priekšnosacījumi

### 1. Programmatūras uzstādīšana
```bash
# Node.js (versija 16+)
# Lejupielādējiet no: https://nodejs.org/

# Java JDK (versija 11+)
# Lejupielādējiet no: https://www.oracle.com/java/technologies/javase-downloads.html

# Android Studio
# Lejupielādējiet no: https://developer.android.com/studio
```

### 2. Android SDK konfigurācija
1. Atveriet Android Studio
2. Dodieties uz Tools -> SDK Manager
3. Uzstādiet Android SDK (API level 30+)
4. Uzstādiet Android Build Tools

### 3. Vides mainīgie (Windows)
```bash
set ANDROID_HOME=C:\Users\YourName\AppData\Local\Android\Sdk
set PATH=%PATH%;%ANDROID_HOME%\tools;%ANDROID_HOME%\platform-tools
```

### 3. Vides mainīgie (Linux/Mac)
```bash
export ANDROID_HOME=$HOME/Android/Sdk
export PATH=$PATH:$ANDROID_HOME/tools:$ANDROID_HOME/platform-tools
```

## APK Izveides Process

### Metode 1: Automātiskā (ieteicamā)
```bash
# Palaidiet build skriptu
chmod +x build_android.sh
./build_android.sh
```

### Metode 2: Manuālā
```bash
# 1. Uzstādīt dependencies
npm install

# 2. Pievienot Android platformu
npx cap add android

# 3. Sinhronizēt failus
npx cap sync

# 4. Atvērt Android Studio
npx cap open android

# 5. Android Studio:
#    - Build -> Build Bundle(s)/APK(s) -> Build APK(s)
#    - Gaidiet build procesu
#    - APK: android/app/build/outputs/apk/debug/app-debug.apk
```

## APK Instalācijas Instrukcijas

### Mehāniķiem un Menedžeriem
1. **Lejupielādēt APK** - Saņemiet `avoti-tms-debug.apk` failu
2. **Iespējot nezināmus avotus**:
   - Iestatījumi -> Drošība -> Nezināmi avoti (ieslēgt)
   - Vai: Iestatījumi -> Apps -> Speciālas piekļuves tiesības -> Instalēt nezināmas aplikācijas
3. **Instalēt**: Atveriet APK failu un sekojiet instalācijas norādījumiem
4. **Palaist**: Atveriet AVOTI TMS aplikāciju no aplikāciju saraksta

## Funkcionalitātes Salīdzinājums

| Funkcija | Web Versija | Android APK |
|----------|-------------|-------------|
| Uzdevumu pārvaldība | ✅ | ✅ |
| Push paziņojumi | ✅ (begāts) | ✅ (natīvi) |
| Offline funkcionalitāte | ✅ (ierobežota) | ✅ (uzlabota) |
| Failu augšupielāde | ✅ | ✅ |
| Kamera piekļuve | ✅ | ✅ |
| Ātrāka ielāde | ❌ | ✅ |
| App store izplatīšana | ❌ | ✅ |
| Automātiskas atjaunināšanas | ✅ | ❌ |

## Problēmu risināšana

### "Java nav atrasta"
```bash
# Pārbaudīt Java instalāciju
java -version
javac -version

# Uzstādīt Java JDK, ja nav
```

### "Android SDK nav atrasts"
1. Atveriet Android Studio
2. Tools -> SDK Manager
3. Uzstādiet Android SDK
4. Konfigurējiet ANDROID_HOME

### "Gradle build neizdevās"
```bash
# Notīrīt build cache
cd android
./gradlew clean
./gradlew assembleDebug
```

### "APK neinstalējas"
1. Pārbaudīt vai ir iespējoti nezināmi avoti
2. Pārbaudīt vai ir pietiekami daudz vietas
3. Izmēģināt instalēt ar ADB:
```bash
adb install avoti-tms-debug.apk
```

## Atjaunināšanas Process

### Web versija
- Atjaunināšanas notiek automātiski
- Service Worker atjaunina kešu
- Nav nepieciešama lietotāja darbība

### Android APK
1. Izveidojiet jaunu APK versiju
2. Palieliniet version code failā `capacitor.config.ts`
3. Izplatiet jauno APK mehāniķiem/menedžeriem
4. Lietotāji instalē jauno versiju manuāli

## Izplatīšanas Stratēģija

### Iekšējai izmantošanai (Ieteicamā)
1. **Web + PWA**: Primārā pieeja visiem lietotājiem
2. **Android APK**: Papildu opcija mehāniķiem lauka darbam

### Publiskai izplatīšanai (Nākotnes opcija)
1. Sagatavot production APK ar signed certificate
2. Iesniegt Google Play Store
3. Sagatavot app store aprakstu un attēlus
4. Iegūt app store apstiprinājumu

## Drošības Apsvērumi

- APK fālos iekļauj servera URL konfigurāciju
- Ieteicams izmantot HTTPS savienojumu
- Production versijās jānoņem debug opcijas
- Jāpārbauda tīkla drošības konfigurācija

## Atbalsts

- Web versija: Darbojas visos modernos pārlūkos
- Android APK: Android 7.0+ (API level 24+)
- iOS: PWA caur Safari (natīva iOS app ir papildu projekts)

Ar šādu risinājumu jūs saglabājat abas opcijas - gan web pieejamību, gan natīvo Android aplikāciju mehāniķu un menedžeru ērtībām.

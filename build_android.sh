
#!/bin/bash

echo "🚀 Sākam AVOTI TMS Android APK būvēšanu lokālajam tīklam..."

echo "📋 SVARĪGI: Pirms turpināt, pārbaudiet:"
echo "   1. Vai jūsu LAMP serveris ir pieejams lokālajā tīklā"
echo "   2. Atjauniniet capacitor.config.ts ar savu IP adresi"
echo "   3. Pārliecinieties, ka serveris atļauj savienojumus no citām ierīcēm"
echo ""

# Pārbaudīt vai ir uzstādīts Node.js
if ! command -v node &> /dev/null; then
    echo "⚠️ Node.js nav atrasts PATH. Mēģinam uzstādīt..."
    
    # Replit vidē mēģinām uzstādīt caur nix
    if command -v nix &> /dev/null; then
        echo "📦 Uzstādam Node.js caur nix..."
        nix-env -iA nixpkgs.nodejs_20
        
        # Atjaunot PATH
        export PATH="/home/runner/.nix-profile/bin:$PATH"
        
        # Pārbaudīt vēlreiz
        if ! command -v node &> /dev/null; then
            echo "❌ Node.js uzstādīšana neizdevās. Restartējiet Repl un mēģiniet vēlreiz."
            exit 1
        fi
    else
        echo "❌ Nav iespējams uzstādīt Node.js. Lūdzu restartējiet Repl."
        exit 1
    fi
fi

echo "✅ Node.js versija: $(node --version)"

# Pārbaudīt vai ir uzstādīts Java
if ! command -v java &> /dev/null; then
    echo "⚠️ Java nav atrasta PATH. Mēģinam uzstādīt..."
    
    # Replit vidē mēģinām uzstādīt caur nix
    if command -v nix &> /dev/null; then
        echo "📦 Uzstādam Java JDK caur nix..."
        # Mēģinām uzstādīt pieejamo Java versiju
        nix-env -iA nixpkgs.openjdk11 || nix-env -iA nixpkgs.openjdk || nix-env -iA nixpkgs.openjdk8
        
        # Atjaunot PATH
        export PATH="/home/runner/.nix-profile/bin:$PATH"
        export JAVA_HOME="/home/runner/.nix-profile"
        
        # Pārbaudīt vēlreiz
        if ! command -v java &> /dev/null; then
            echo "❌ Java uzstādīšana neizdevās. Mēģinām alternatīvu metodi..."
            
            # Alternatīva metode - install caur Replit modules
            echo "📦 Mēģinām uzstādīt Java caur replit.nix..."
            if [ ! -f "replit.nix" ]; then
                cat > replit.nix << 'EOF'
{ pkgs }: {
  deps = [
    pkgs.openjdk11
    pkgs.nodejs_20
    pkgs.android-tools
  ];
}
EOF
                echo "⚠️ Izveidots replit.nix fails. Lūdzu restartējiet Repl un palaižiet build vēlreiz."
                exit 1
            fi
        fi
    else
        echo "❌ Nav iespējams uzstādīt Java. Lūdzu restartējiet Repl."
        exit 1
    fi
fi

# Uzstādīt Capacitor CLI, ja nav uzstādīts
if ! command -v cap &> /dev/null; then
    echo "📦 Uzstādam Capacitor CLI..."
    
    # Izmantot npx, ja npm nav pieejams globāli
    if command -v npm &> /dev/null; then
        npm install -g @capacitor/cli
    elif command -v npx &> /dev/null; then
        echo "Izmantojam npx Capacitor komandām..."
        # Izveidot alias cap funkciju
        cap() {
            npx @capacitor/cli "$@"
        }
        export -f cap
    else
        echo "❌ npm/npx nav pieejams"
        exit 1
    fi
fi

# Uzstādīt project dependencies
echo "📦 Uzstādam projekta dependencies..."
npm install

# Pievienot Android platform, ja nav pievienota
echo "🔧 Konfigurējam Android platformu..."
npx cap add android 2>/dev/null || echo "Android platform jau ir pievienota"

# Sinhronizēt assets un konfigurāciju
echo "🔄 Sinhronizējam failus..."
npx cap sync

# Pārkopēt ikonu failus
echo "🎨 Konfigurējam ikonas..."
mkdir -p android/app/src/main/res/mipmap-hdpi
mkdir -p android/app/src/main/res/mipmap-mdpi
mkdir -p android/app/src/main/res/mipmap-xhdpi
mkdir -p android/app/src/main/res/mipmap-xxhdpi
mkdir -p android/app/src/main/res/mipmap-xxxhdpi

# Pārkopēt ikonas (izmantojot esošās PWA ikonas)
cp assets/images/icon-72x72.png android/app/src/main/res/mipmap-hdpi/ic_launcher.png 2>/dev/null || echo "Ikona 72x72 nav atrasta"
cp assets/images/icon-96x96.png android/app/src/main/res/mipmap-mdpi/ic_launcher.png 2>/dev/null || echo "Ikona 96x96 nav atrasta"
cp assets/images/icon-128x128.png android/app/src/main/res/mipmap-xhdpi/ic_launcher.png 2>/dev/null || echo "Ikona 128x128 nav atrasta"
cp assets/images/icon-192x192.png android/app/src/main/res/mipmap-xxhdpi/ic_launcher.png 2>/dev/null || echo "Ikona 192x192 nav atrasta"
cp assets/images/icon-384x384.png android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png 2>/dev/null || echo "Ikona 384x384 nav atrasta"

echo "📱 Veidojam Android APK..."

# Atvērt Android Studio projektu build
if command -v gradle &> /dev/null; then
    echo "🏗️ Izmantojam Gradle tiešo build..."
    cd android
    ./gradlew assembleDebug
    cd ..
    
    if [ -f "android/app/build/outputs/apk/debug/app-debug.apk" ]; then
        echo "✅ APK veiksmīgi izveidots!"
        echo "📍 APK atrašanās vieta: android/app/build/outputs/apk/debug/app-debug.apk"
        
        # Pārkopēt APK uz saknes mapi ērtībai
        cp android/app/build/outputs/apk/debug/app-debug.apk ./avoti-tms-debug.apk
        echo "📱 APK pārkopēts: ./avoti-tms-debug.apk"
        
        echo ""
        echo "🎉 AVOTI TMS Android aplikācija ir gatava!"
        echo "📥 Lejupielādējiet un uzstādiet: avoti-tms-debug.apk"
        echo ""
        echo "📋 Nākamie soļi:"
        echo "   1. Pārsūtiet APK failu uz Android ierīci"
        echo "   2. Iespējojiet 'Unknown sources' ierīces iestatījumos"
        echo "   3. Uzstādiet APK failu"
        echo "   4. Atveriet AVOTI TMS aplikāciju"
        
    else
        echo "❌ APK build neizdevās"
        exit 1
    fi
else
    echo "📱 Atverām Android Studio projektam..."
    npx cap open android
    echo ""
    echo "📋 Android Studio atvērts. Turpiniet šādos soļos:"
    echo "   1. Android Studio: Build -> Build Bundle(s)/APK(s) -> Build APK(s)"
    echo "   2. Gaidiet build procesu"
    echo "   3. APK būs pieejams: android/app/build/outputs/apk/debug/"
fi

echo ""
echo "🌐 Web versija joprojām būs pieejama caur pārlūku!"
echo "📱 PWA instalācija joprojām darbojas Chrome pārlūkā"

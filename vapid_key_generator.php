<?php
/**
 * VAPID Atslēgu Ģenerētājs
 * Palaidiet šo failu komandas rindā vai pārlūkā, lai ģenerētu VAPID atslēgas
 * 
 * Izmantošana:
 * php generate_vapid_keys.php
 */

function generateVapidKeys() {
    // Metode 1: Izmantojot OpenSSL (ieteicamā)
    if (function_exists('openssl_pkey_new')) {
        echo "Ģenerē VAPID atslēgas ar OpenSSL...\n\n";
        
        // Ģenerēt privāto atslēgu
        $config = [
            "curve_name" => "prime256v1",
            "private_key_type" => OPENSSL_KEYTYPE_EC,
        ];
        
        $private_key = openssl_pkey_new($config);
        if (!$private_key) {
            die("Kļūda ģenerējot privāto atslēgu\n");
        }
        
        // Eksportēt privāto atslēgu
        openssl_pkey_export($private_key, $private_key_pem);
        
        // Iegūt publisko atslēgu
        $public_key_details = openssl_pkey_get_details($private_key);
        $public_key_pem = $public_key_details['key'];
        
        // Konvertēt uz base64url formātu
        $private_key_raw = getPrivateKeyRaw($private_key_pem);
        $public_key_raw = getPublicKeyRaw($public_key_pem);
        
        $private_key_base64 = base64UrlEncode($private_key_raw);
        $public_key_base64 = base64UrlEncode($public_key_raw);
        
        echo "✅ VAPID atslēgas veiksmīgi ģenerētas!\n\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "Pievienojiet šīs rindas jūsu config.php failā:\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";
        echo "// VAPID Keys for Push Notifications\n";
        echo "define('VAPID_PUBLIC_KEY', '{$public_key_base64}');\n";
        echo "define('VAPID_PRIVATE_KEY', '{$private_key_base64}');\n";
        echo "define('VAPID_CONTACT', 'mailto:admin@avoti.lv');\n\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";
        
        // Saglabāt failā
        $keys_content = "<?php\n";
        $keys_content .= "// VAPID Keys generated on " . date('Y-m-d H:i:s') . "\n";
        $keys_content .= "define('VAPID_PUBLIC_KEY', '{$public_key_base64}');\n";
        $keys_content .= "define('VAPID_PRIVATE_KEY', '{$private_key_base64}');\n";
        $keys_content .= "define('VAPID_CONTACT', 'mailto:admin@avoti.lv');\n";
        $keys_content .= "?>";
        
        file_put_contents('vapid_keys.php', $keys_content);
        echo "💾 Atslēgas saglabātas failā: vapid_keys.php\n";
        echo "⚠️  SVARĪGI: Saglabājiet šīs atslēgas drošā vietā un nedalieties ar privāto atslēgu!\n\n";
        
    } else {
        // Metode 2: Izmantojot ārējo servisu (rezerves variants)
        echo "OpenSSL nav pieejams. Izmantojiet online ģenerātoru:\n";
        echo "🌐 https://vapidkeys.com/\n";
        echo "🌐 https://web-push-codelab.glitch.me/\n\n";
        echo "Vai instalējiet web-push bibliotēku:\n";
        echo "npm install -g web-push\n";
        echo "web-push generate-vapid-keys\n\n";
    }
}

function getPrivateKeyRaw($private_key_pem) {
    // Ekstraktē raw bytes no PEM formāta
    $private_key_pem = str_replace("-----BEGIN EC PRIVATE KEY-----", "", $private_key_pem);
    $private_key_pem = str_replace("-----END EC PRIVATE KEY-----", "", $private_key_pem);
    $private_key_pem = str_replace("\n", "", $private_key_pem);
    
    $private_key_der = base64_decode($private_key_pem);
    
    // P-256 privātā atslēga ir 32 bytes
    // Ekstraktējam pēdējos 32 bytes no DER formāta
    return substr($private_key_der, -32);
}

function getPublicKeyRaw($public_key_pem) {
    // Ekstraktē raw bytes no PEM formāta
    $public_key_details = openssl_pkey_get_details(openssl_pkey_get_public($public_key_pem));
    $public_key_raw = $public_key_details['ec']['xy'];
    
    // Pievienojam 0x04 prefix (uncompressed point indicator)
    return "\x04" . $public_key_raw;
}

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Palaist funkciju
if (php_sapi_name() === 'cli') {
    echo "AVOTI TMS - VAPID Atslēgu Ģenerētājs\n";
    echo "═══════════════════════════════════════\n\n";
    generateVapidKeys();
} else {
    // Web versija
    ?>
    <!DOCTYPE html>
    <html lang="lv">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>VAPID Atslēgu Ģenerētājs</title>
        <style>
            body { font-family: monospace; max-width: 800px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
            .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .keys-output { background: #f8f9fa; padding: 20px; border-radius: 4px; border-left: 4px solid #28a745; margin: 20px 0; }
            .warning { background: #fff3cd; padding: 15px; border-radius: 4px; border-left: 4px solid #ffc107; margin: 20px 0; }
            button { background: #007cba; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            button:hover { background: #005a87; }
            code { background: #e9ecef; padding: 2px 4px; border-radius: 2px; }
            pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔐 AVOTI TMS - VAPID Atslēgu Ģenerētājs</h1>
            
            <?php if (isset($_POST['generate'])): ?>
                <div class="keys-output">
                    <?php
                    ob_start();
                    generateVapidKeys();
                    $output = ob_get_clean();
                    echo '<pre>' . htmlspecialchars($output) . '</pre>';
                    ?>
                </div>
            <?php else: ?>
                <p>VAPID (Voluntary Application Server Identification) atslēgas ir nepieciešamas push notification drošībai.</p>
                
                <form method="POST">
                    <button type="submit" name="generate">Ģenerēt VAPID Atslēgas</button>
                </form>
                
                <div class="warning">
                    <strong>⚠️ Svarīgi:</strong>
                    <ul>
                        <li>Privāto atslēgu nekad nedalieties ar citiem</li>
                        <li>Saglabājiet atslēgas drošā vietā</li>
                        <li>Ja zaudējat atslēgas, visiem lietotājiem būs jāatjauno push subscription</li>
                    </ul>
                </div>
                
                <h3>Alternatīvie veidi:</h3>
                <p>Ja šis ģenerātors nedarbojas:</p>
                <ul>
                    <li>Izmantojiet online: <a href="https://vapidkeys.com/" target="_blank">vapidkeys.com</a></li>
                    <li>Izmantojiet Node.js: <code>npm install -g web-push && web-push generate-vapid-keys</code></li>
                </ul>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}
?>
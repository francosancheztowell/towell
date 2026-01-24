<?php

if (!function_exists('getDeviceInfo')) {
    /**
     * Obtiene información del dispositivo basándose en el User Agent
     *
     * @return array
     */
    function getDeviceInfo(): array
    {
        $userAgent = request()->userAgent() ?? '';
        $ip = request()->ip();

        return [
            'tipo' => detectDeviceType($userAgent),
            'navegador' => detectBrowser($userAgent),
            'sistema' => detectOS($userAgent),
            'ip' => $ip,
            'user_agent' => $userAgent,
        ];
    }
}

if (!function_exists('detectDeviceType')) {
    /**
     * Detecta el tipo de dispositivo con modelo específico cuando es posible
     *
     * @param string $userAgent
     * @return array ['nombre' => string, 'modelo' => string, 'icono' => string, 'tipo' => string]
     */
    function detectDeviceType(string $userAgent): array
    {
        // Detectar modelo específico primero
        $modelo = detectDeviceModel($userAgent);

        // Tablets (verificar antes que móviles)
        // Samsung tablets: SM-T (Tab), SM-X (Tab), SM-P (Tab con S Pen)
        $tablets = [
            'ipad' => ['nombre' => 'iPad', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            // iPadOS en modo "desktop" puede reportar Macintosh + Mobile
            'macintosh.*mobile' => ['nombre' => 'iPad', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'sm-[txp]\d{2,4}[a-z]?' => ['nombre' => 'Samsung Galaxy Tab', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'galaxy\s*tab' => ['nombre' => 'Samsung Galaxy Tab', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            // Detectar Samsung por User Agent que contiene Android pero NO Mobile
            'samsung.*android(?!.*mobile)' => ['nombre' => 'Samsung Galaxy Tab', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'lenovo.*tb-|tb-[a-z0-9]{4,7}' => ['nombre' => 'Lenovo Tab', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'lenovo.*tab' => ['nombre' => 'Lenovo Tab', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'huawei.*(matepad|mediapad)' => ['nombre' => 'Huawei MatePad/MediaPad', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'honor\s*pad' => ['nombre' => 'Honor Pad', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'xiaomi\s*pad|mi\s*pad|redmi\s*pad' => ['nombre' => 'Xiaomi Pad', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'oneplus\s*pad|opd\d{3,4}' => ['nombre' => 'OnePlus Pad', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'nexus\s*7|nexus\s*9|pixel\s*c' => ['nombre' => 'Google Tablet', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'zenpad|asus\s*pad' => ['nombre' => 'ASUS Tablet', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'tab\s*[aps]\d' => ['nombre' => 'Tablet', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            // Android sin Mobile = Tablet (muy com?n en tablets)
            'android(?!.*mobile)' => ['nombre' => 'Tablet Android', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'tablet' => ['nombre' => 'Tablet', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'kindle' => ['nombre' => 'Kindle', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'silk' => ['nombre' => 'Kindle Fire', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'kf[a-z0-9]{3,5}' => ['nombre' => 'Kindle Fire', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'playbook' => ['nombre' => 'BlackBerry PlayBook', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
            'surface' => ['nombre' => 'Microsoft Surface', 'icono' => 'fa-tablet', 'tipo' => 'tablet'],
        ];

        foreach ($tablets as $pattern => $info) {
            if (preg_match('/' . $pattern . '/i', $userAgent)) {
                $info['modelo'] = $modelo;
                return $info;
            }
        }

        // Móviles
        $mobiles = [
            'iphone' => ['nombre' => 'iPhone', 'icono' => 'fa-mobile-screen', 'tipo' => 'mobile'],
            'sm-[gans]\d{3}' => ['nombre' => 'Samsung Galaxy', 'icono' => 'fa-mobile-screen', 'tipo' => 'mobile'],
            'pixel' => ['nombre' => 'Google Pixel', 'icono' => 'fa-mobile-screen', 'tipo' => 'mobile'],
            'oneplus' => ['nombre' => 'OnePlus', 'icono' => 'fa-mobile-screen', 'tipo' => 'mobile'],
            'huawei' => ['nombre' => 'Huawei', 'icono' => 'fa-mobile-screen', 'tipo' => 'mobile'],
            'xiaomi|redmi|poco' => ['nombre' => 'Xiaomi', 'icono' => 'fa-mobile-screen', 'tipo' => 'mobile'],
            'oppo' => ['nombre' => 'Oppo', 'icono' => 'fa-mobile-screen', 'tipo' => 'mobile'],
            'vivo' => ['nombre' => 'Vivo', 'icono' => 'fa-mobile-screen', 'tipo' => 'mobile'],
            'motorola|moto\s' => ['nombre' => 'Motorola', 'icono' => 'fa-mobile-screen', 'tipo' => 'mobile'],
            'android.*mobile' => ['nombre' => 'Android', 'icono' => 'fa-mobile-screen', 'tipo' => 'mobile'],
            'windows phone' => ['nombre' => 'Windows Phone', 'icono' => 'fa-mobile-screen', 'tipo' => 'mobile'],
            'blackberry' => ['nombre' => 'BlackBerry', 'icono' => 'fa-mobile-screen', 'tipo' => 'mobile'],
            'mobile' => ['nombre' => 'Móvil', 'icono' => 'fa-mobile-screen', 'tipo' => 'mobile'],
        ];

        foreach ($mobiles as $pattern => $info) {
            if (preg_match('/' . $pattern . '/i', $userAgent)) {
                $info['modelo'] = $modelo;
                return $info;
            }
        }

        // Verificación adicional: Android sin Mobile generalmente es Tablet
        // Muchas tablets Samsung/Lenovo no incluyen "Mobile" en el User Agent
        if (preg_match('/android/i', $userAgent) && !preg_match('/mobile/i', $userAgent)) {
            // Si tiene Samsung en el User Agent, es Galaxy Tab
            if (preg_match('/samsung/i', $userAgent)) {
                return [
                    'nombre' => 'Samsung Galaxy Tab',
                    'modelo' => $modelo,
                    'icono' => 'fa-tablet',
                    'tipo' => 'tablet'
                ];
            }
            // Si tiene Lenovo, es Lenovo Tab
            if (preg_match('/lenovo/i', $userAgent)) {
                return [
                    'nombre' => 'Lenovo Tab',
                    'modelo' => $modelo,
                    'icono' => 'fa-tablet',
                    'tipo' => 'tablet'
                ];
            }
            // Cualquier Android sin Mobile = Tablet
            return [
                'nombre' => 'Tablet Android',
                'modelo' => $modelo,
                'icono' => 'fa-tablet',
                'tipo' => 'tablet'
            ];
        }

        // Desktop por defecto
        return ['nombre' => 'Computadora', 'modelo' => $modelo, 'icono' => 'fa-desktop', 'tipo' => 'desktop'];
    }
}

if (!function_exists('detectDeviceModel')) {
    /**
     * Intenta detectar el modelo específico del dispositivo
     *
     * @param string $userAgent
     * @return string
     */
    function detectDeviceModel(string $userAgent): string
    {
        // iPadOS en modo desktop puede reportar Macintosh + Mobile
        if (!preg_match('/iPad|iPhone/i', $userAgent) && preg_match('/Macintosh/i', $userAgent) && preg_match('/Mobile\/[A-Z0-9]+/i', $userAgent)) {
            return 'iPad';
        }

        // iPad - detectar generación si es posible
        if (preg_match('/iPad/i', $userAgent)) {
            if (preg_match('/\b(iPad\d{1,2},\d{1,2})\b/i', $userAgent, $matches)) {
                return 'iPad (' . $matches[1] . ')';
            }
            if (preg_match('/iPad.*OS\s(\d+)/i', $userAgent, $matches)) {
                $iosVersion = (int)$matches[1];
                // iOS 17+ generalmente es iPad 10th gen o Pro reciente
                if ($iosVersion >= 17) return 'iPad (10ª gen o Pro)';
                if ($iosVersion >= 15) return 'iPad (9ª gen+)';
                if ($iosVersion >= 13) return 'iPad (7ª gen+)';
            }
            return 'iPad';
        }

        // iPhone - detectar modelo aproximado por versión de iOS
        if (preg_match('/iPhone.*OS\s(\d+)_(\d+)/i', $userAgent, $matches)) {
            $iosVersion = (int)$matches[1];
            if ($iosVersion >= 17) return 'iPhone 15/14/SE 3';
            if ($iosVersion >= 16) return 'iPhone 14/13/SE 3';
            if ($iosVersion >= 15) return 'iPhone 13/12/SE 2';
            if ($iosVersion >= 14) return 'iPhone 12/11/SE 2';
            return 'iPhone';
        }

        // Samsung - detectar modelo específico
        // Formatos: SM-T220, SM-X200, SM-P610, SM-T970, SM-A536, SM-S918, SM-G998, etc.
        if (preg_match('/SM-([A-Z]\d{2,4}[A-Z]?)/i', $userAgent, $matches)) {
            $model = strtoupper($matches[1]);
            $firstChar = substr($model, 0, 1);
            $modelNum = substr($model, 1, 3);
            
            // Galaxy Tab series (T, X, P)
            if (in_array($firstChar, ['T', 'X', 'P'])) {
                $tabName = getSamsungTabName($model);
                return $tabName ?: ('Galaxy Tab SM-' . $model);
            }
            
            // Galaxy S series (S, G para modelos antiguos)
            if ($firstChar === 'S' || $firstChar === 'G') {
                if ($firstChar === 'S') {
                    $num = (int)$modelNum;
                    if ($num >= 900) return 'Galaxy S24/S23 Ultra';
                    if ($num >= 700) return 'Galaxy S23/S22';
                    return 'Galaxy S Series';
                }
                if ($firstChar === 'G') {
                    return 'Galaxy S Series (SM-G' . $modelNum . ')';
                }
            }
            
            // Galaxy A series
            if ($firstChar === 'A') {
                return 'Galaxy A' . $modelNum;
            }
            
            // Galaxy M series
            if ($firstChar === 'M') {
                return 'Galaxy M' . $modelNum;
            }
            
            // Galaxy F series
            if ($firstChar === 'F') {
                return 'Galaxy F' . $modelNum;
            }
            
            // Galaxy Note series (N)
            if ($firstChar === 'N') {
                return 'Galaxy Note SM-N' . $modelNum;
            }
            
            return 'Samsung SM-' . $model;
        }
        
        // Fallback: buscar "Galaxy Tab" en el User Agent
        if (preg_match('/Galaxy\s*Tab\s*([^\s;)]+)?/i', $userAgent, $matches)) {
            return 'Galaxy Tab ' . trim($matches[1] ?? '');
        }
        
        // Samsung sin código SM- pero con Android (probablemente tablet)
        if (preg_match('/samsung/i', $userAgent) && preg_match('/android/i', $userAgent) && !preg_match('/mobile/i', $userAgent)) {
            // Intentar extraer cualquier identificador numérico/alfanumérico después de Samsung
            if (preg_match('/samsung[^;]*?([A-Z0-9]{6,10})/i', $userAgent, $matches)) {
                $id = strtoupper($matches[1]);
                // Si parece un ID de dispositivo (como 311C0240)
                if (preg_match('/^[0-9A-F]{6,10}$/i', $id)) {
                    return 'Galaxy Tab (' . $id . ')';
                }
            }
            return 'Galaxy Tab';
        }

        // Google Pixel
        if (preg_match('/Pixel\s*(\d+[a-z]?)/i', $userAgent, $matches)) {
            return 'Pixel ' . $matches[1];
        }

        // Xiaomi/Redmi/POCO
        if (preg_match('/(Redmi\s*Note\s*\d+|Redmi\s*\d+|POCO\s*[A-Z]\d+|Mi\s*\d+)/i', $userAgent, $matches)) {
            return $matches[1];
        }

        // Surface
        if (preg_match('/Surface\s*(Pro\s*\d+|Go\s*\d*|Laptop\s*\d*|Book\s*\d*)?/i', $userAgent, $matches)) {
            return 'Surface ' . trim($matches[1] ?? '');
        }

        // Lenovo tablets - capturar modelo completo
        // Formatos comunes: "Lenovo TB-X606F", "Lenovo TB-J606F", "Lenovo Tab M10", "TB-8505F"
        if (preg_match('/(?:Lenovo\s*)?(TB-[A-Z0-9]+[A-Z]?)/i', $userAgent, $matches)) {
            $model = strtoupper($matches[1]);
            return 'Lenovo ' . $model . ' ' . getLenovoModelName($model);
        }
        
        // Lenovo TAB 2 A10-70, TAB 4 10, etc.
        if (preg_match('/Lenovo\s*TAB\s*([0-9]+\s*[A-Z0-9-]+)/i', $userAgent, $matches)) {
            return 'Lenovo TAB ' . trim($matches[1]);
        }

        // Lenovo Tab con nombre
        if (preg_match('/Lenovo\s+(Tab\s*[A-Z]?\d+[^;)\s]*)/i', $userAgent, $matches)) {
            return 'Lenovo ' . trim($matches[1]);
        }
        
        // Cualquier Lenovo
        if (preg_match('/Lenovo\s+([^;)\s]+)/i', $userAgent, $matches)) {
            return 'Lenovo ' . trim($matches[1]);
        }

        // Huawei tablets
        if (preg_match('/\bHUAWEI\s*((?:MediaPad|MatePad)[^;)]*)/i', $userAgent, $matches)) {
            return 'Huawei ' . trim($matches[1] ?? '');
        }

        // Honor Pad
        if (preg_match('/\bHONOR\s*(Pad[^;)]*)/i', $userAgent, $matches)) {
            return 'Honor ' . trim($matches[1]);
        }

        // Xiaomi / Redmi tablets
        if (preg_match('/\b(Xiaomi\s+Pad\s+[^;)\s]+|Mi\s+Pad\s+\d+|Redmi\s+Pad\s+[^;)\s]+)/i', $userAgent, $matches)) {
            return trim($matches[1]);
        }

        // OnePlus Pad (OPD2203, etc.)
        if (preg_match('/\bOnePlus\s+Pad[^;)]*/i', $userAgent, $matches)) {
            return trim($matches[0]);
        }
        if (preg_match('/\bOPD\d{3,4}\b/i', $userAgent, $matches)) {
            return 'OnePlus Pad (' . strtoupper($matches[0]) . ')';
        }

        // Amazon Kindle/Fire (KF* codes)
        if (preg_match('/\bKF[A-Z0-9]{3,5}\b/i', $userAgent, $matches) && preg_match('/(Kindle|Silk|Amazon)/i', $userAgent)) {
            return 'Kindle Fire (' . strtoupper($matches[0]) . ')';
        }

        // Google tablets
        if (preg_match('/\b(Nexus\s*[79]|Pixel\s*C)\b/i', $userAgent, $matches)) {
            return trim($matches[0]);
        }

        // ASUS tablets
        if (preg_match('/\b(ASUS\s*ZenPad[^;)]*|ASUS\s*Pad[^;)]*)/i', $userAgent, $matches)) {
            return trim($matches[1]);
        }

        // Android generico: extraer modelo antes de "Build/"
        if (preg_match('/Android\s[\d.]+;\s*([^;)]*?)\s+Build\//i', $userAgent, $matches)) {
            $model = trim($matches[1]);
            if ($model !== '' && !preg_match('/^(?:Linux|Android)$/i', $model)) {
                return $model;
            }
        }

        return '';
    }
}

if (!function_exists('detectBrowser')) {
    /**
     * Detecta el navegador
     *
     * @param string $userAgent
     * @return array ['nombre' => string, 'version' => string, 'icono' => string]
     */
    function detectBrowser(string $userAgent): array
    {
        $browsers = [
            'Edg' => ['nombre' => 'Edge', 'icono' => 'fa-edge', 'pattern' => '/Edg(?:e|A|iOS)?\/([0-9.]+)/i'],
            'OPR' => ['nombre' => 'Opera', 'icono' => 'fa-opera', 'pattern' => '/OPR\/([0-9.]+)/i'],
            'Opera' => ['nombre' => 'Opera', 'icono' => 'fa-opera', 'pattern' => '/Opera\/([0-9.]+)/i'],
            'Chrome' => ['nombre' => 'Chrome', 'icono' => 'fa-chrome', 'pattern' => '/Chrome\/([0-9.]+)/i'],
            'Safari' => ['nombre' => 'Safari', 'icono' => 'fa-safari', 'pattern' => '/Version\/([0-9.]+).*Safari/i'],
            'Firefox' => ['nombre' => 'Firefox', 'icono' => 'fa-firefox', 'pattern' => '/Firefox\/([0-9.]+)/i'],
            'MSIE' => ['nombre' => 'Internet Explorer', 'icono' => 'fa-internet-explorer', 'pattern' => '/MSIE ([0-9.]+)/i'],
            'Trident' => ['nombre' => 'Internet Explorer', 'icono' => 'fa-internet-explorer', 'pattern' => '/rv:([0-9.]+)/i'],
        ];

        foreach ($browsers as $key => $browser) {
            if (stripos($userAgent, $key) !== false) {
                $version = '';
                if (preg_match($browser['pattern'], $userAgent, $matches)) {
                    $version = $matches[1] ?? '';
                    // Solo mostrar versión mayor
                    $version = explode('.', $version)[0];
                }
                return [
                    'nombre' => $browser['nombre'],
                    'version' => $version,
                    'icono' => $browser['icono'],
                ];
            }
        }

        return ['nombre' => 'Desconocido', 'version' => '', 'icono' => 'fa-globe'];
    }
}

if (!function_exists('detectOS')) {
    /**
     * Detecta el sistema operativo
     *
     * @param string $userAgent
     * @return array ['nombre' => string, 'version' => string, 'icono' => string]
     */
    function detectOS(string $userAgent): array
    {
        $systems = [
            // Windows
            ['pattern' => '/Windows NT 10/i', 'nombre' => 'Windows 10/11', 'icono' => 'fa-windows'],
            ['pattern' => '/Windows NT 6.3/i', 'nombre' => 'Windows 8.1', 'icono' => 'fa-windows'],
            ['pattern' => '/Windows NT 6.2/i', 'nombre' => 'Windows 8', 'icono' => 'fa-windows'],
            ['pattern' => '/Windows NT 6.1/i', 'nombre' => 'Windows 7', 'icono' => 'fa-windows'],
            ['pattern' => '/Windows/i', 'nombre' => 'Windows', 'icono' => 'fa-windows'],

            // macOS / iOS
            ['pattern' => '/iPhone OS ([0-9_]+)/i', 'nombre' => 'iOS', 'icono' => 'fa-apple', 'version_pattern' => '/iPhone OS ([0-9_]+)/i'],
            ['pattern' => '/iPad.*OS ([0-9_]+)/i', 'nombre' => 'iPadOS', 'icono' => 'fa-apple', 'version_pattern' => '/OS ([0-9_]+)/i'],
            ['pattern' => '/Mac OS X ([0-9_]+)/i', 'nombre' => 'macOS', 'icono' => 'fa-apple', 'version_pattern' => '/Mac OS X ([0-9_]+)/i'],
            ['pattern' => '/Macintosh/i', 'nombre' => 'macOS', 'icono' => 'fa-apple'],

            // Android
            ['pattern' => '/Android ([0-9.]+)/i', 'nombre' => 'Android', 'icono' => 'fa-android', 'version_pattern' => '/Android ([0-9.]+)/i'],
            ['pattern' => '/Android/i', 'nombre' => 'Android', 'icono' => 'fa-android'],

            // Linux
            ['pattern' => '/Ubuntu/i', 'nombre' => 'Ubuntu', 'icono' => 'fa-ubuntu'],
            ['pattern' => '/Linux/i', 'nombre' => 'Linux', 'icono' => 'fa-linux'],

            // Chrome OS
            ['pattern' => '/CrOS/i', 'nombre' => 'Chrome OS', 'icono' => 'fa-chrome'],
        ];

        foreach ($systems as $system) {
            if (preg_match($system['pattern'], $userAgent, $matches)) {
                $version = '';
                if (isset($system['version_pattern']) && preg_match($system['version_pattern'], $userAgent, $vMatches)) {
                    $version = str_replace('_', '.', $vMatches[1] ?? '');
                    // Solo mostrar versión mayor.menor
                    $parts = explode('.', $version);
                    $version = $parts[0] . (isset($parts[1]) ? '.' . $parts[1] : '');
                }
                return [
                    'nombre' => $system['nombre'],
                    'version' => $version,
                    'icono' => $system['icono'],
                ];
            }
        }

        return ['nombre' => 'Desconocido', 'version' => '', 'icono' => 'fa-question-circle'];
    }
}

if (!function_exists('getSamsungTabName')) {
    /**
     * Traduce el código de modelo Samsung Tab a nombre comercial
     *
     * @param string $modelCode (sin el prefijo SM-)
     * @return string
     */
    function getSamsungTabName(string $modelCode): string
    {
        $modelCode = strtoupper($modelCode);
        
        // Mapeo de códigos a nombres comerciales
        $models = [
            // Galaxy Tab S Series (Premium)
            'T870' => 'Galaxy Tab S7',
            'T875' => 'Galaxy Tab S7 5G',
            'T970' => 'Galaxy Tab S7+',
            'T976' => 'Galaxy Tab S7+ 5G',
            'X700' => 'Galaxy Tab S8',
            'X706' => 'Galaxy Tab S8 5G',
            'X800' => 'Galaxy Tab S8+',
            'X806' => 'Galaxy Tab S8+ 5G',
            'X900' => 'Galaxy Tab S8 Ultra',
            'X906' => 'Galaxy Tab S8 Ultra 5G',
            'X710' => 'Galaxy Tab S9',
            'X716' => 'Galaxy Tab S9 5G',
            'X810' => 'Galaxy Tab S9+',
            'X816' => 'Galaxy Tab S9+ 5G',
            'X910' => 'Galaxy Tab S9 Ultra',
            'X916' => 'Galaxy Tab S9 Ultra 5G',
            'X510' => 'Galaxy Tab S9 FE',
            'X516' => 'Galaxy Tab S9 FE 5G',
            'X610' => 'Galaxy Tab S9 FE+',
            'X616' => 'Galaxy Tab S9 FE+ 5G',
            'T860' => 'Galaxy Tab S6',
            'T865' => 'Galaxy Tab S6 LTE',
            'T720' => 'Galaxy Tab S5e',
            'T725' => 'Galaxy Tab S5e LTE',
            
            // Galaxy Tab S Lite (con S Pen)
            'P610' => 'Galaxy Tab S6 Lite',
            'P615' => 'Galaxy Tab S6 Lite LTE',
            'P613' => 'Galaxy Tab S6 Lite (2022)',
            'P619' => 'Galaxy Tab S6 Lite (2022) LTE',
            'P620' => 'Galaxy Tab S6 Lite (2024)',
            'P625' => 'Galaxy Tab S6 Lite (2024) LTE',
            
            // Galaxy Tab A Series
            'T500' => 'Galaxy Tab A7',
            'T505' => 'Galaxy Tab A7 LTE',
            'T220' => 'Galaxy Tab A7 Lite',
            'T225' => 'Galaxy Tab A7 Lite LTE',
            'T290' => 'Galaxy Tab A 8.0 (2019)',
            'T295' => 'Galaxy Tab A 8.0 (2019) LTE',
            'T510' => 'Galaxy Tab A 10.1 (2019)',
            'T515' => 'Galaxy Tab A 10.1 (2019) LTE',
            'T590' => 'Galaxy Tab A 10.5',
            'T595' => 'Galaxy Tab A 10.5 LTE',
            'T307' => 'Galaxy Tab A 8.4 (2020)',
            
            // Galaxy Tab A8
            'X200' => 'Galaxy Tab A8',
            'X205' => 'Galaxy Tab A8 LTE',
            
            // Galaxy Tab A9
            'X110' => 'Galaxy Tab A9',
            'X115' => 'Galaxy Tab A9 LTE',
            'X210' => 'Galaxy Tab A9+',
            'X215' => 'Galaxy Tab A9+ LTE',
            
            // Galaxy Tab Active (Rugged)
            'T570' => 'Galaxy Tab Active3',
            'T575' => 'Galaxy Tab Active3 LTE',
            'T630' => 'Galaxy Tab Active4 Pro',
            'T636' => 'Galaxy Tab Active4 Pro 5G',
            'X300' => 'Galaxy Tab Active5',
            'X306' => 'Galaxy Tab Active5 5G',
            
            // Galaxy Tab E / Tab 4 (Legacy)
            'T560' => 'Galaxy Tab E 9.6',
            'T561' => 'Galaxy Tab E 9.6 LTE',
            'T530' => 'Galaxy Tab 4 10.1',
            'T535' => 'Galaxy Tab 4 10.1 LTE',
            'T330' => 'Galaxy Tab 4 8.0',
            'T335' => 'Galaxy Tab 4 8.0 LTE',
            'T230' => 'Galaxy Tab 4 7.0',
            'T235' => 'Galaxy Tab 4 7.0 LTE',
        ];
        
        // Buscar coincidencia exacta
        if (isset($models[$modelCode])) {
            return $models[$modelCode];
        }
        
        // Buscar por los primeros 3-4 caracteres (sin sufijo de letra)
        $baseModel = preg_replace('/[A-Z]$/', '', $modelCode);
        if (isset($models[$baseModel])) {
            return $models[$baseModel];
        }
        
        // Identificar serie por patrón
        $firstChar = substr($modelCode, 0, 1);
        $num = (int)substr($modelCode, 1, 3);
        
        if ($firstChar === 'T') {
            if ($num >= 800) return 'Galaxy Tab S Series';
            if ($num >= 500) return 'Galaxy Tab A Series';
            if ($num >= 200) return 'Galaxy Tab A Lite';
            return 'Galaxy Tab';
        }
        
        if ($firstChar === 'X') {
            if ($num >= 700) return 'Galaxy Tab S Series';
            if ($num >= 500) return 'Galaxy Tab S FE';
            if ($num >= 200) return 'Galaxy Tab A Series';
            if ($num >= 100) return 'Galaxy Tab A9';
            return 'Galaxy Tab';
        }
        
        if ($firstChar === 'P') {
            return 'Galaxy Tab S Lite';
        }
        
        return '';
    }
}

if (!function_exists('getLenovoModelName')) {
    /**
     * Traduce el código de modelo Lenovo a nombre comercial
     *
     * @param string $modelCode
     * @return string
     */
    function getLenovoModelName(string $modelCode): string
    {
        $modelCode = strtoupper($modelCode);
        
        // Mapeo de códigos a nombres comerciales
        $models = [
            // Tab M10 Series
            'TB-X606F' => '(Tab M10 FHD Plus)',
            'TB-X606X' => '(Tab M10 FHD Plus LTE)',
            'TB-X306F' => '(Tab M10 HD Gen 2)',
            'TB-X306X' => '(Tab M10 HD Gen 2 LTE)',
            'TB-X605F' => '(Tab M10 FHD)',
            'TB-X605L' => '(Tab M10 FHD LTE)',
            'TB-X505F' => '(Tab M10 HD)',
            'TB-X505L' => '(Tab M10 HD LTE)',
            'TB-X506F' => '(Tab M10 HD Gen 2)',
            'TB-X616F' => '(Tab M10 Plus 3rd Gen)',
            'TB-X616X' => '(Tab M10 Plus 3rd Gen LTE)',
            'TB-128FU' => '(Tab M10 Plus 3rd Gen)',
            
            // Tab M8 Series
            'TB-8505F' => '(Tab M8 HD)',
            'TB-8505X' => '(Tab M8 HD LTE)',
            'TB-8506F' => '(Tab M8 HD Gen 2)',
            'TB-8705F' => '(Tab M8 FHD)',
            'TB-8705N' => '(Tab M8 FHD)',
            'TB-300FU' => '(Tab M8 4th Gen)',
            'TB-300XU' => '(Tab M8 4th Gen LTE)',
            
            // Tab M9 Series
            'TB-310FU' => '(Tab M9)',
            'TB-310XU' => '(Tab M9 LTE)',
            
            // Tab M11 Series
            'TB-J616F' => '(Tab M11)',
            'TB-J616X' => '(Tab M11 LTE)',
            
            // Tab P11 Series
            'TB-J606F' => '(Tab P11)',
            'TB-J606L' => '(Tab P11 LTE)',
            'TB-J607Z' => '(Tab P11 5G)',
            'TB-J706F' => '(Tab P11 Pro)',
            'TB-J706L' => '(Tab P11 Pro LTE)',
            'TB-J716F' => '(Tab P11 Pro Gen 2)',
            'TB-138FC' => '(Tab P11 2nd Gen)',
            'TB-138FU' => '(Tab P11 2nd Gen)',
            
            // Tab P12 Series
            'TB-370FU' => '(Tab P12)',
            'TB-371FC' => '(Tab P12 Pro)',
            
            // Yoga Tab Series
            'YT-X705F' => '(Yoga Smart Tab)',
            'YT-X705L' => '(Yoga Smart Tab LTE)',
            'YT-J706F' => '(Yoga Tab 11)',
            'YT-J706X' => '(Yoga Tab 11 LTE)',
            'YT-K606F' => '(Yoga Tab 13)',
            
            // Tab 4 Series (legacy)
            'TB-X304F' => '(Tab 4 10)',
            'TB-X304L' => '(Tab 4 10 LTE)',
            'TB-8504F' => '(Tab 4 8)',
            'TB-8504X' => '(Tab 4 8 LTE)',
            'TB-7504F' => '(Tab 4 7)',
            'TB-7504X' => '(Tab 4 7 LTE)',
            
            // Tab E Series
            'TB-X104F' => '(Tab E10)',
            'TB-X104L' => '(Tab E10 LTE)',
            'TB-7104F' => '(Tab E7)',
            
            // Otros modelos comunes
            'TB-7305F' => '(Tab M7)',
            'TB-7305X' => '(Tab M7 LTE)',
            'TB-7306F' => '(Tab M7 Gen 2)',
            'TB-7306X' => '(Tab M7 Gen 2 LTE)',
        ];
        
        return $models[$modelCode] ?? '';
    }
}

if (!function_exists('getDeviceIdentifier')) {
    /**
     * Genera un identificador único para el dispositivo basado en características
     * Nota: Este identificador puede cambiar si el usuario actualiza el navegador
     *
     * @return string
     */
    function getDeviceIdentifier(): string
    {
        $userAgent = request()->userAgent() ?? '';
        $ip = request()->ip();
        $acceptLanguage = request()->header('Accept-Language', '');

        // Crear un hash basado en características del dispositivo
        $fingerprint = md5($userAgent . $ip . $acceptLanguage);

        // Retornar versión corta (8 caracteres)
        return strtoupper(substr($fingerprint, 0, 8));
    }
}

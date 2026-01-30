<?php

/**
 * Endpoint para servir arquivos HTML dos projetos Pix4D
 * 
 * Este endpoint atua como proxy, servindo arquivos HTML e seus recursos
 * de forma segura, validando caminhos e prevenindo path traversal.
 */

// Carrega configurações
require_once __DIR__ . '/../config/config.php';

/**
 * Normaliza um caminho removendo . e .. sem verificar se o arquivo existe
 * 
 * @param string $path Caminho a normalizar
 * @return string Caminho normalizado
 */
function normalizePath(string $path): string
{
    // Preserva a raiz do caminho (unidade do Windows ou / no Unix)
    $isAbsolute = false;
    $root = '';
    
    // Detecta caminho absoluto do Windows (ex: D:\ ou D:/)
    if (preg_match('/^([A-Za-z]:)[\/\\\\]/', $path, $matches)) {
        $isAbsolute = true;
        $root = $matches[1] . DIRECTORY_SEPARATOR;
        $path = substr($path, strlen($root));
    } elseif (strpos($path, DIRECTORY_SEPARATOR) === 0 || strpos($path, '/') === 0) {
        // Caminho absoluto Unix
        $isAbsolute = true;
        $root = DIRECTORY_SEPARATOR;
        $path = ltrim($path, '/\\');
    }
    
    $parts = preg_split('/[\/\\\\]+/', $path, -1, PREG_SPLIT_NO_EMPTY);
    $normalized = [];
    
    foreach ($parts as $part) {
        if ($part === '.' || $part === '') {
            continue;
        }
        
        if ($part === '..') {
            if (!empty($normalized)) {
                array_pop($normalized);
            } elseif (!$isAbsolute) {
                // Permite .. no início de caminhos relativos
                $normalized[] = $part;
            }
        } else {
            $normalized[] = $part;
        }
    }
    
    $result = implode(DIRECTORY_SEPARATOR, $normalized);
    
    return $isAbsolute ? $root . $result : $result;
}

/**
 * Valida e sanitiza o caminho do projeto
 * 
 * @param string $relativePath Caminho relativo fornecido
 * @param string|null $baseDir Caminho relativo do diretório base (para resolver caminhos relativos simples)
 * @return string|null Caminho absoluto validado ou null se inválido
 */
function validateAndResolvePath(string $relativePath, ?string $baseDir = null): ?string
{
    // Remove caracteres perigosos e normaliza
    $relativePath = str_replace(['..', '\\'], ['', '/'], $relativePath);
    $relativePath = trim($relativePath, '/');
    
    // Se baseDir foi fornecido e o caminho parece ser um caminho relativo simples (ex: tiles)
    // tenta resolver em relação ao baseDir
    if ($baseDir !== null && !empty($baseDir)) {
        // Constrói o caminho completo: baseDir + relativePath
        $fullPath = $baseDir . '/' . $relativePath;
        $fullPath = normalizePath(BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fullPath));
        
        // Verifica segurança: deve estar dentro do BASE_PATH
        $basePathConfig = normalizePath(BASE_PATH);
        $fullPathNormalized = normalizePath($fullPath);
        
        if (strcasecmp(substr($fullPathNormalized, 0, strlen($basePathConfig)), $basePathConfig) === 0) {
            // Verifica se o arquivo existe
            if (file_exists($fullPath) && is_readable($fullPath)) {
                return $fullPath;
            }
            
            // Para tiles, verifica se o diretório pai existe (tiles podem ser gerados dinamicamente)
            // Mas só permite se o caminho parece ser um tile (números/caminho de tile)
            if (preg_match('/^[\d\/\w\-\.]+\.(png|jpg|jpeg|gif)$/i', $relativePath)) {
                $parentDir = dirname($fullPath);
                if (is_dir($parentDir) && is_readable($parentDir)) {
                    // Verifica se o diretório pai está dentro do BASE_PATH
                    $parentNormalized = normalizePath($parentDir);
                    if (strcasecmp(substr($parentNormalized, 0, strlen($basePathConfig)), $basePathConfig) === 0) {
                        return $fullPath; // Retorna mesmo se não existir, para permitir criação dinâmica
                    }
                }
            }
        }
    }
    
    // Constrói caminho absoluto usando BASE_PATH configurado
    $absolutePath = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    
    // Normaliza o caminho (resolve .. e .)
    $absolutePath = normalizePath($absolutePath);
    
    // Verifica se o arquivo existe e é legível
    // Como há um link simbólico (mklink), o realpath pode apontar para outro lugar,
    // mas se o arquivo foi construído a partir do BASE_PATH configurado e existe, é válido
    if (file_exists($absolutePath) && is_readable($absolutePath)) {
        // Verifica segurança básica: o caminho construído deve começar com BASE_PATH configurado
        $basePathConfig = normalizePath(BASE_PATH);
        $absolutePathNormalized = normalizePath($absolutePath);
        
        // Compara case-insensitive no Windows
        if (strcasecmp(substr($absolutePathNormalized, 0, strlen($basePathConfig)), $basePathConfig) === 0) {
            // Arquivo válido - pode usar realpath para normalizar, mas não é obrigatório
            $resolvedPath = realpath($absolutePath);
            return $resolvedPath !== false ? $resolvedPath : $absolutePath;
        }
        
        // Se não corresponde ao BASE_PATH configurado, verifica se é devido a link simbólico
        // Neste caso, verifica se o diretório pai está dentro do BASE_PATH
        $parentDir = dirname($absolutePath);
        $parentNormalized = normalizePath($parentDir);
        
        if (strcasecmp(substr($parentNormalized, 0, strlen($basePathConfig)), $basePathConfig) === 0) {
            // Diretório pai está dentro do BASE_PATH, arquivo é válido
            $resolvedPath = realpath($absolutePath);
            return $resolvedPath !== false ? $resolvedPath : $absolutePath;
        }
    }
    
    return null;
}

/**
 * Serve um arquivo HTML ajustando os caminhos relativos
 * 
 * @param string $filePath Caminho absoluto do arquivo HTML
 */
function serveHtmlFile(string $filePath): void
{
    $content = file_get_contents($filePath);
    
    if ($content === false) {
        http_response_code(500);
        echo 'Erro ao ler arquivo';
        return;
    }
    
    // Injeta a chave do Google Maps se estiver configurada
    if (defined('GOOGLE_MAPS_API_KEY') && !empty(GOOGLE_MAPS_API_KEY)) {
        $apiKey = GOOGLE_MAPS_API_KEY;
        
        // Função auxiliar para processar URL do Google Maps
        $processGoogleMapsUrl = function($url) use ($apiKey) {
            // Remove chave existente se houver
            $url = preg_replace('/[?&]key=[^&"\'\s<>]*/i', '', $url);
            // Remove sensor=false ou sensor=true (formato antigo)
            $url = preg_replace('/[?&]sensor=[^&"\'\s<>]*/i', '', $url);
            // Limpa múltiplos && ou ?&
            $url = preg_replace('/\?&+/', '?', $url);
            $url = preg_replace('/&+/', '&', $url);
            $url = rtrim($url, '&?');
            // Adiciona nossa chave
            $separator = (strpos($url, '?') !== false) ? '&' : '?';
            return $url . $separator . 'key=' . urlencode($apiKey);
        };
        
        // PRIMEIRO: Substituição mais simples e direta - captura o padrão exato do HTML Pix4D
        // Padrão específico: <script type="text/javascript" src="https://maps.google.com/maps/api/js?sensor=false"></script>
        // Este padrão deve ser executado ANTES dos outros para garantir que funcione
        // Captura qualquer variação: com ou sem espaços, com ou sem type, etc.
        
        // Substituição 1: Padrão mais comum do Pix4D
        $content = str_replace(
            'https://maps.google.com/maps/api/js?sensor=false',
            'https://maps.googleapis.com/maps/api/js?key=' . urlencode($apiKey),
            $content
        );
        
        // Substituição 2: Com http em vez de https
        $content = str_replace(
            'http://maps.google.com/maps/api/js?sensor=false',
            'https://maps.googleapis.com/maps/api/js?key=' . urlencode($apiKey),
            $content
        );
        
        // Substituição 3: Com espaços no sensor=false
        $content = preg_replace(
            '/https?:\/\/maps\.google\.com\/maps\/api\/js\?sensor\s*=\s*false/i',
            'https://maps.googleapis.com/maps/api/js?key=' . urlencode($apiKey),
            $content
        );
        
        // Substituição 4: Com sensor=true também
        $content = preg_replace(
            '/https?:\/\/maps\.google\.com\/maps\/api\/js\?sensor\s*=\s*true/i',
            'https://maps.googleapis.com/maps/api/js?key=' . urlencode($apiKey),
            $content
        );
        
        // Substituição 5: Qualquer variação com maps.google.com
        $content = preg_replace_callback(
            '/https?:\/\/maps\.google\.com\/maps\/api\/js([^"\'<>\s]*)/i',
            function($matches) use ($apiKey) {
                $params = $matches[1];
                // Remove sensor se existir
                $params = preg_replace('/[?&]sensor=[^&"\']*/i', '', $params);
                // Remove key se existir
                $params = preg_replace('/[?&]key=[^&"\']*/i', '', $params);
                // Limpa
                $params = preg_replace('/\?&+/', '?', $params);
                $params = preg_replace('/&+/', '&', $params);
                $params = rtrim($params, '&?');
                // Adiciona key
                $separator = (strpos($params, '?') !== false) ? '&' : '?';
                return 'https://maps.googleapis.com/maps/api/js' . $params . $separator . 'key=' . urlencode($apiKey);
            },
            $content
        );
        
        // Padrão 1: URLs completas com protocolo - maps.googleapis.com
        // Captura: https://maps.googleapis.com/maps/api/js?...
        $googleMapsPattern1 = '/(https?:\/\/maps\.googleapis\.com\/maps\/api\/js[^"\'<>\s]*)/i';
        $content = preg_replace_callback($googleMapsPattern1, function($matches) use ($processGoogleMapsUrl) {
            return $processGoogleMapsUrl($matches[1]);
        }, $content);
        
        // Padrão 1b: URLs completas com protocolo - maps.google.com (formato antigo)
        // Captura: https://maps.google.com/maps/api/js?...
        $googleMapsPattern1b = '/(https?:\/\/maps\.google\.com\/maps\/api\/js[^"\'<>\s]*)/i';
        $content = preg_replace_callback($googleMapsPattern1b, function($matches) use ($processGoogleMapsUrl, $apiKey) {
            // Converte maps.google.com para maps.googleapis.com e processa
            $url = str_replace('maps.google.com', 'maps.googleapis.com', $matches[1]);
            $processed = $processGoogleMapsUrl($url);
            // Debug em desenvolvimento
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                error_log('[Google Maps] Substituído: ' . $matches[1] . ' -> ' . $processed);
            }
            return $processed;
        }, $content);
        
        // Padrão 2: URLs sem protocolo - maps.googleapis.com
        $googleMapsPattern2 = '/(\/\/maps\.googleapis\.com\/maps\/api\/js[^"\'<>\s]*)/i';
        $content = preg_replace_callback($googleMapsPattern2, function($matches) use ($processGoogleMapsUrl) {
            return $processGoogleMapsUrl($matches[1]);
        }, $content);
        
        // Padrão 2b: URLs sem protocolo - maps.google.com (formato antigo)
        $googleMapsPattern2b = '/(\/\/maps\.google\.com\/maps\/api\/js[^"\'<>\s]*)/i';
        $content = preg_replace_callback($googleMapsPattern2b, function($matches) use ($processGoogleMapsUrl) {
            // Converte maps.google.com para maps.googleapis.com e processa
            $url = str_replace('maps.google.com', 'maps.googleapis.com', $matches[1]);
            return $processGoogleMapsUrl($url);
        }, $content);
        
        // Padrão 3: Em atributos src e href (com aspas simples ou duplas) - maps.googleapis.com
        $content = preg_replace_callback(
            '/(src|href)\s*=\s*["\']([^"\']*maps\.googleapis\.com\/maps\/api\/js[^"\']*)["\']/i',
            function($matches) use ($processGoogleMapsUrl) {
                $attr = $matches[1];
                $url = $processGoogleMapsUrl($matches[2]);
                $quote = strpos($matches[0], '"') !== false ? '"' : "'";
                return $attr . '=' . $quote . $url . $quote;
            },
            $content
        );
        
        // Padrão 3b: Em atributos src e href (com aspas simples ou duplas) - maps.google.com
        // Este é o padrão mais importante pois é o usado nos arquivos Pix4D
        $content = preg_replace_callback(
            '/(src|href)\s*=\s*["\']([^"\']*maps\.google\.com\/maps\/api\/js[^"\']*)["\']/i',
            function($matches) use ($processGoogleMapsUrl, $apiKey) {
                $attr = $matches[1];
                $originalUrl = $matches[2];
                // Converte maps.google.com para maps.googleapis.com e processa
                $url = str_replace('maps.google.com', 'maps.googleapis.com', $originalUrl);
                $url = $processGoogleMapsUrl($url);
                $quote = strpos($matches[0], '"') !== false ? '"' : "'";
                
                // Debug em desenvolvimento
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    error_log('[Google Maps] Padrão 3b - Original: ' . $originalUrl);
                    error_log('[Google Maps] Padrão 3b - Processado: ' . $url);
                }
                
                return $attr . '=' . $quote . $url . $quote;
            },
            $content
        );
        
        // Padrão 4: URLs sem aspas (menos comum, mas possível) - maps.googleapis.com
        $content = preg_replace_callback(
            '/(src|href)\s*=\s*([^\s>]*maps\.googleapis\.com\/maps\/api\/js[^\s<>]*)/i',
            function($matches) use ($processGoogleMapsUrl) {
                $attr = $matches[1];
                $url = $processGoogleMapsUrl($matches[2]);
                return $attr . '="' . $url . '"';
            },
            $content
        );
        
        // Padrão 4b: URLs sem aspas - maps.google.com
        $content = preg_replace_callback(
            '/(src|href)\s*=\s*([^\s>]*maps\.google\.com\/maps\/api\/js[^\s<>]*)/i',
            function($matches) use ($processGoogleMapsUrl) {
                $attr = $matches[1];
                // Converte maps.google.com para maps.googleapis.com e processa
                $url = str_replace('maps.google.com', 'maps.googleapis.com', $matches[2]);
                $url = $processGoogleMapsUrl($url);
                return $attr . '="' . $url . '"';
            },
            $content
        );
        
        // Padrão 5: Captura URLs que podem estar em scripts inline ou variáveis JavaScript
        // Procura por padrões como: "maps.googleapis.com/maps/api/js?sensor=false"
        $content = preg_replace_callback(
            '/(["\']?)(https?:)?(\/\/)?maps\.googleapis\.com\/maps\/api\/js([^"\'<>\s]*)(["\']?)/i',
            function($matches) use ($processGoogleMapsUrl, $apiKey) {
                $before = $matches[1];
                $protocol = $matches[2] ?: 'https:';
                $slashes = $matches[3] ?: '//';
                $params = $matches[4];
                $after = $matches[5];
                
                // Reconstrói a URL completa
                $fullUrl = $protocol . $slashes . 'maps.googleapis.com/maps/api/js' . $params;
                $processedUrl = $processGoogleMapsUrl($fullUrl);
                
                // Remove o protocolo e // se não estava presente originalmente
                if (empty($matches[2])) {
                    $processedUrl = preg_replace('/^https?:\/\//', '', $processedUrl);
                }
                
                return $before . $processedUrl . $after;
            },
            $content
        );
        
        // Padrão 5b: Captura maps.google.com em scripts inline
        $content = preg_replace_callback(
            '/(["\']?)(https?:)?(\/\/)?maps\.google\.com\/maps\/api\/js([^"\'<>\s]*)(["\']?)/i',
            function($matches) use ($processGoogleMapsUrl, $apiKey) {
                $before = $matches[1];
                $protocol = $matches[2] ?: 'https:';
                $slashes = $matches[3] ?: '//';
                $params = $matches[4];
                $after = $matches[5];
                
                // Reconstrói a URL completa convertendo para maps.googleapis.com
                $fullUrl = $protocol . $slashes . 'maps.googleapis.com/maps/api/js' . $params;
                $processedUrl = $processGoogleMapsUrl($fullUrl);
                
                // Remove o protocolo e // se não estava presente originalmente
                if (empty($matches[2])) {
                    $processedUrl = preg_replace('/^https?:\/\//', '', $processedUrl);
                }
                
                return $before . $processedUrl . $after;
            },
            $content
        );
        
        // Debug: verifica se há URLs do Google Maps ainda sem chave (apenas em desenvolvimento)
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            $hasUnprocessedUrl = preg_match('/maps\.googleapis\.com\/maps\/api\/js[^"\'<>]*sensor=/i', $content) ||
                                preg_match('/maps\.google\.com\/maps\/api\/js[^"\'<>]*sensor=/i', $content);
            if ($hasUnprocessedUrl) {
                error_log('[Google Maps] AVISO: Encontrada URL do Google Maps com sensor= que não foi processada');
            }
            // Verifica se a substituição funcionou
            $hasKey = preg_match('/maps\.googleapis\.com\/maps\/api\/js[^"\'<>]*key=' . preg_quote(urlencode($apiKey), '/') . '/i', $content);
            if ($hasKey) {
                error_log('[Google Maps] OK: Chave encontrada no HTML processado');
            } else {
                error_log('[Google Maps] AVISO: Chave NÃO encontrada no HTML processado');
            }
        }
        
        // Script robusto que intercepta ANTES do carregamento e DEPOIS também
        $googleMapsKeyScript = '
    <script>
    (function() {
        var apiKey = "' . addslashes($apiKey) . '";
        
        // Debug: verifica se a chave foi carregada (remover em produção)
        console.log("[Google Maps] Chave API configurada:", apiKey ? "SIM (" + apiKey.substring(0, 10) + "...)" : "NÃO");
        
        // Função para adicionar/atualizar chave em uma URL
        function addApiKeyToUrl(url) {
            // Verifica se url é uma string
            if (typeof url !== "string" || !url) {
                return url;
            }
            // Verifica se é uma URL do Google Maps (suporta ambos os formatos)
            var isGoogleMaps = url.indexOf("maps.googleapis.com/maps/api/js") !== -1 ||
                              url.indexOf("maps.google.com/maps/api/js") !== -1;
            if (!isGoogleMaps) {
                return url;
            }
            // Converte maps.google.com para maps.googleapis.com (formato atual)
            url = url.replace(/maps\.google\.com\/maps\/api\/js/i, "maps.googleapis.com/maps/api/js");
            // Remove chave existente
            url = url.replace(/[?&]key=[^&]*/i, "");
            // Remove sensor=false (formato antigo, substitui pela chave)
            url = url.replace(/[?&]sensor=[^&]*/i, "");
            // Adiciona nossa chave
            var separator = url.indexOf("?") !== -1 ? "&" : "?";
            return url + separator + "key=" + encodeURIComponent(apiKey);
        }
        
        // Intercepta ANTES do DOM estar pronto (executa imediatamente)
        (function interceptBeforeLoad() {
            // Intercepta criação de elementos script
            var originalCreateElement = document.createElement;
            document.createElement = function(tagName) {
                var element = originalCreateElement.call(document, tagName);
                if (tagName.toLowerCase() === "script") {
                    var originalSetAttribute = element.setAttribute;
                    element.setAttribute = function(name, value) {
                        if (name === "src" && value) {
                            // Converte para string se necessário
                            var strValue = typeof value === "string" ? value : String(value || "");
                            var newValue = addApiKeyToUrl(strValue);
                            if (newValue !== strValue && typeof newValue === "string") {
                                console.log("[Google Maps] Interceptado setAttribute:", newValue);
                            }
                            return originalSetAttribute.call(this, name, newValue || strValue);
                        }
                        return originalSetAttribute.call(this, name, value);
                    };
                    
                    // Intercepta também a propriedade src
                    Object.defineProperty(element, "src", {
                        set: function(value) {
                            // Converte para string se necessário
                            var strValue = typeof value === "string" ? value : String(value || "");
                            var newValue = addApiKeyToUrl(strValue);
                            if (newValue !== strValue && typeof newValue === "string") {
                                console.log("[Google Maps] Interceptado src:", newValue);
                            }
                            element.setAttribute("src", newValue || strValue);
                        },
                        get: function() {
                            return element.getAttribute("src");
                        },
                        configurable: true
                    });
                }
                return element;
            };
        })();
        
        // Intercepta scripts já existentes quando o DOM estiver pronto
        function interceptExistingScripts() {
            var scripts = document.getElementsByTagName("script");
            var found = false;
            var updated = false;
            console.log("[Google Maps] Verificando " + scripts.length + " scripts...");
            
            for (var i = 0; i < scripts.length; i++) {
                var src = scripts[i].src || scripts[i].getAttribute("src") || "";
                var innerHTML = scripts[i].innerHTML || "";
                
                // Garante que src é uma string
                if (typeof src !== "string") {
                    continue;
                }
                
                // Verifica se é uma URL do Google Maps (com ou sem domínio completo)
                // Suporta tanto maps.googleapis.com quanto maps.google.com (formato antigo)
                var isGoogleMaps = src.indexOf("maps.googleapis.com/maps/api/js") !== -1 || 
                                  src.indexOf("maps.google.com/maps/api/js") !== -1 ||
                                  src.indexOf("/maps/api/js") !== -1;
                
                // Também verifica no innerHTML (para scripts inline que podem ter a URL)
                if (!isGoogleMaps && innerHTML) {
                    isGoogleMaps = innerHTML.indexOf("maps.googleapis.com/maps/api/js") !== -1 ||
                                  innerHTML.indexOf("maps.google.com/maps/api/js") !== -1;
                }
                
                if (src && isGoogleMaps) {
                    found = true;
                    console.log("[Google Maps] Script encontrado:", src);
                    var originalSrc = src;
                    var newSrc = addApiKeyToUrl(src);
                    
                    // Se a URL tem sensor mas não tem key, força substituição
                    if (src.indexOf("sensor=") !== -1 && src.indexOf("key=") === -1) {
                        newSrc = addApiKeyToUrl(src);
                        if (typeof newSrc === "string" && newSrc !== src) {
                            console.log("[Google Maps] FORÇANDO substituição de sensor por key:", newSrc);
                            scripts[i].src = newSrc;
                            updated = true;
                        }
                    } else if (newSrc !== src && typeof newSrc === "string") {
                        console.log("[Google Maps] Atualizando script existente:", newSrc);
                        scripts[i].src = newSrc;
                        updated = true;
                    } else if (src.indexOf("key=") === -1) {
                        // Se não tem chave de forma alguma, adiciona
                        newSrc = addApiKeyToUrl(src);
                        if (typeof newSrc === "string" && newSrc !== src) {
                            console.log("[Google Maps] Adicionando chave ao script:", newSrc);
                            scripts[i].src = newSrc;
                            updated = true;
                        }
                    } else {
                        console.log("[Google Maps] Script já tem chave, mantendo:", src);
                    }
                }
            }
            
            // Se não encontrou nenhum script do Google Maps, tenta criar um novo
            if (!found) {
                console.log("[Google Maps] Nenhum script do Google Maps encontrado - criando novo script");
                var newScript = document.createElement("script");
                newScript.src = "https://maps.googleapis.com/maps/api/js?key=" + encodeURIComponent(apiKey);
                newScript.async = true;
                newScript.defer = true;
                
                // Adiciona callback para quando o script carregar
                newScript.onload = function() {
                    console.log("[Google Maps] Script do Google Maps carregado com sucesso!");
                    // Aguarda um pouco mais para garantir que google.maps está totalmente disponível
                    setTimeout(function() {
                        if (window.google && window.google.maps) {
                            console.log("[Google Maps] Google Maps API está pronta!");
                            // Dispara evento customizado para notificar que o Google Maps está pronto
                            var event = new CustomEvent("googlemapsready");
                            window.dispatchEvent(event);
                        }
                    }, 100);
                };
                
                document.head.appendChild(newScript);
                console.log("[Google Maps] Novo script do Google Maps criado e adicionado ao head");
                found = true;
                updated = true;
            } else {
                // Se encontrou script existente, aguarda seu carregamento
                var mapsSelector1 = "script[src*=\'maps.googleapis.com\']";
                var mapsSelector2 = "script[src*=\'maps.google.com\']";
                var existingScripts = document.querySelectorAll(mapsSelector1 + ", " + mapsSelector2);
                existingScripts.forEach(function(script) {
                    if (!script.hasAttribute("data-googlemaps-waiter")) {
                        script.setAttribute("data-googlemaps-waiter", "true");
                        script.onload = function() {
                            console.log("[Google Maps] Script existente do Google Maps carregado!");
                            setTimeout(function() {
                                if (window.google && window.google.maps) {
                                    console.log("[Google Maps] Google Maps API está pronta!");
                                    var event = new CustomEvent("googlemapsready");
                                    window.dispatchEvent(event);
                                }
                            }, 100);
                        };
                    }
                });
            }
            
            if (!found) {
                // Lista todos os scripts para debug
                console.log("[Google Maps] Scripts encontrados no DOM:");
                for (var j = 0; j < scripts.length; j++) {
                    var s = scripts[j].src || scripts[j].getAttribute("src") || "(sem src)";
                    console.log("  - Script " + j + ": " + s);
                }
            } else if (updated) {
                console.log("[Google Maps] Script(s) atualizado(s) com sucesso!");
            }
        }
        
        // Função para aguardar Google Maps estar pronto antes de executar código que depende dele
        function waitForGoogleMaps(callback) {
            if (window.google && window.google.maps && window.google.maps.Map) {
                console.log("[Google Maps] Google Maps já está pronto, executando callback");
                callback();
            } else {
                console.log("[Google Maps] Aguardando Google Maps carregar...");
                // Aguarda evento customizado
                var handler = function() {
                    console.log("[Google Maps] Evento googlemapsready recebido, executando callback");
                    window.removeEventListener("googlemapsready", handler);
                    callback();
                };
                window.addEventListener("googlemapsready", handler);
                
                // Fallback: verifica periodicamente
                var attempts = 0;
                var checkInterval = setInterval(function() {
                    attempts++;
                    if (window.google && window.google.maps && window.google.maps.Map) {
                        console.log("[Google Maps] Google Maps detectado após " + attempts + " tentativas");
                        clearInterval(checkInterval);
                        callback();
                    } else if (attempts > 50) {
                        console.warn("[Google Maps] Timeout aguardando Google Maps (50 tentativas)");
                        clearInterval(checkInterval);
                    }
                }, 100);
            }
        }
        
        // Intercepta chamadas para initialize() e outras funções que usam google.maps
        // Aguarda o Google Maps estar pronto antes de executar
        
        // Intercepta body.onload ANTES de qualquer coisa
        // Isso precisa ser feito o mais cedo possível
        function interceptBodyOnload() {
            // Verifica se body já existe
            if (document.body) {
                var bodyOnloadAttr = document.body.getAttribute("onload");
                if (bodyOnloadAttr) {
                    console.log("[Google Maps] Interceptando body.onload:", bodyOnloadAttr);
                    document.body.removeAttribute("onload");
                    waitForGoogleMaps(function() {
                        console.log("[Google Maps] Executando body.onload após Google Maps estar pronto");
                        try {
                            // Cria uma função wrapper para executar o código
                            var func = new Function(bodyOnloadAttr);
                            func();
                        } catch(e) {
                            console.error("[Google Maps] Erro ao executar body.onload:", e);
                        }
                    });
                }
            }
        }
        
        // Tenta interceptar imediatamente
        interceptBodyOnload();
        
        // Também tenta quando o DOM estiver pronto
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", interceptBodyOnload);
        }
        
        // Intercepta window.onload
        var originalOnload = window.onload;
        window.onload = function(event) {
            console.log("[Google Maps] window.onload disparado, aguardando Google Maps...");
            waitForGoogleMaps(function() {
                console.log("[Google Maps] Executando window.onload original");
                if (originalOnload) {
                    originalOnload(event);
                }
            });
        };
        
        // Executa imediatamente
        interceptExistingScripts();
        
        // Executa quando o DOM estiver pronto
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", interceptExistingScripts);
        } else {
            setTimeout(interceptExistingScripts, 0);
        }
        
        // Executa também após delays para pegar scripts carregados dinamicamente
        setTimeout(interceptExistingScripts, 50);
        setTimeout(interceptExistingScripts, 100);
        setTimeout(interceptExistingScripts, 300);
        setTimeout(interceptExistingScripts, 500);
        setTimeout(interceptExistingScripts, 1000);
        
        // Observa mudanças no DOM para scripts adicionados dinamicamente
        if (window.MutationObserver) {
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeName === "SCRIPT") {
                            var src = node.src || node.getAttribute("src") || "";
                            if (typeof src === "string" && src) {
                                var isGoogleMaps = src.indexOf("maps.googleapis.com/maps/api/js") !== -1 || 
                                                  src.indexOf("/maps/api/js") !== -1;
                                if (isGoogleMaps) {
                                    var newSrc = addApiKeyToUrl(src);
                                    if (newSrc !== src && typeof newSrc === "string") {
                                        console.log("[Google Maps] Script adicionado dinamicamente, atualizando:", newSrc);
                                        node.src = newSrc;
                                    }
                                }
                            }
                        }
                    });
                });
            });
            observer.observe(document, { childList: true, subtree: true });
        }
        
        // Intercepta também requisições de rede (fetch e XMLHttpRequest)
        // Isso pode ajudar se o Google Maps carregar scripts via AJAX
        if (window.fetch) {
            var originalFetch = window.fetch;
            window.fetch = function(input, init) {
                if (typeof input === "string") {
                    var newInput = addApiKeyToUrl(input);
                    if (newInput !== input) {
                        console.log("[Google Maps] Interceptado fetch:", newInput);
                        input = newInput;
                    }
                }
                return originalFetch.call(this, input, init);
            };
        }
    })();
    </script>';
    } else {
        $googleMapsKeyScript = '';
    }
    
    // Obtém o diretório do arquivo para ajustar caminhos relativos
    $fileDir = dirname($filePath);
    $baseDir = realpath(BASE_PATH);
    $basePathReal = realpath($baseDir);
    
    // Calcula o caminho relativo do diretório do arquivo em relação ao BASE_PATH
    $relativeDir = str_replace($basePathReal, '', $fileDir);
    $relativeDir = str_replace('\\', '/', $relativeDir);
    $relativeDir = trim($relativeDir, '/');
    
    // Prepara dados para o script de interceptação
    $baseDirEncoded = urlencode($relativeDir);
    
    // Injeta script JavaScript que intercepta requisições de recursos
    // Isso é necessário porque o Google Maps gera URLs dinamicamente via JavaScript
    
    // Calcula o caminho do endpoint baseado na URL atual
    // O script JavaScript vai calcular dinamicamente usando window.location
    $interceptorScript = '
    <script>
    (function() {
        var baseDir = "' . addslashes($relativeDir) . '";
        var baseDirEncoded = "' . addslashes($baseDirEncoded) . '";
        
        // Calcula o caminho do endpoint baseado na URL atual
        var currentUrl = window.location.href;
        var urlObj = new URL(currentUrl);
        var pathParts = urlObj.pathname.split("/");
        // Remove partes vazias e encontra api/
        var apiIndex = pathParts.indexOf("api");
        var apiEndpoint = "";
        if (apiIndex !== -1) {
            // Reconstrói o caminho até api/
            apiEndpoint = pathParts.slice(0, apiIndex + 1).join("/") + "/view_project.php";
        } else {
            // Fallback: usa caminho relativo
            apiEndpoint = "api/view_project.php";
        }
        
        function normalizePath(path) {
            var parts = path.split("/");
            var normalized = [];
            for (var i = 0; i < parts.length; i++) {
                if (parts[i] === "..") {
                    if (normalized.length > 0) normalized.pop();
                } else if (parts[i] !== "." && parts[i] !== "") {
                    normalized.push(parts[i]);
                }
            }
            return normalized.join("/");
        }
        
        function convertToApiUrl(url) {
            if (!url || url.match(/^(https?:|data:|javascript:|mailto:|\/)/)) {
                return url;
            }
            // Ignora URLs inválidas ou vazias
            if (url === "none.png" || url === "none" || url.trim() === "" || url.indexOf("://") !== -1) {
                return url; // Mantém original para não quebrar
            }
            // Ignora se já é uma URL do endpoint
            if (url.indexOf("view_project.php") !== -1) {
                return url;
            }
            var fullPath = baseDir + "/" + url.replace(/^\.\//, "");
            var normalized = normalizePath(fullPath);
            return apiEndpoint + "?path=" + encodeURIComponent(normalized) + "&base=" + baseDirEncoded;
        }
        
        // Intercepta todas as atribuições de src e href usando MutationObserver
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === "attributes") {
                    var target = mutation.target;
                    if (mutation.attributeName === "src" && target.src) {
                        var newSrc = convertToApiUrl(target.getAttribute("src"));
                        if (newSrc !== target.src) {
                            target.src = newSrc;
                        }
                    }
                }
            });
        });
        
        observer.observe(document, {
            attributes: true,
            attributeFilter: ["src", "href"],
            subtree: true
        });
        
        // Intercepta criação de elementos
        var originalCreateElement = document.createElement;
        document.createElement = function(tagName) {
            var element = originalCreateElement.call(document, tagName);
            
            if (tagName.toLowerCase() === "img") {
                // Intercepta src via propriedade
                var srcDescriptor = Object.getOwnPropertyDescriptor(HTMLImageElement.prototype, "src") || 
                                    Object.getOwnPropertyDescriptor(HTMLElement.prototype, "src");
                
                if (srcDescriptor) {
                    Object.defineProperty(element, "src", {
                        set: function(value) {
                            value = convertToApiUrl(value);
                            srcDescriptor.set.call(this, value);
                        },
                        get: function() {
                            return srcDescriptor.get.call(this);
                        },
                        configurable: true
                    });
                }
            }
            
            return element;
        };
        
        // Intercepta XMLHttpRequest
        var originalOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
            url = convertToApiUrl(url);
            return originalOpen.call(this, method, url, async, user, password);
        };
        
        // Intercepta fetch
        if (window.fetch) {
            var originalFetch = window.fetch;
            window.fetch = function(input, init) {
                if (typeof input === "string") {
                    input = convertToApiUrl(input);
                } else if (input && input.url) {
                    input = new Request(convertToApiUrl(input.url), input);
                }
                return originalFetch.call(this, input, init);
            };
        }
    })();
    </script>';
    
    // Injeta CSS para garantir que o mapa ocupe toda a área disponível
    $mapStyles = '
    <style>
    html, body {
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
        overflow: hidden;
        box-sizing: border-box;
    }
    body > div:first-child {
        width: 100% !important;
        height: 100% !important;
    }
    /* Força elementos do Google Maps a ocupar toda a área */
    .gm-style {
        width: 100% !important;
        height: 100% !important;
    }
    </style>
    <script>
    // Força redimensionamento do Google Maps quando a página carregar
    (function() {
        function resizeMap() {
            if (window.google && window.google.maps) {
                // Encontra todos os mapas e força resize
                var maps = document.querySelectorAll("[id*=\'map\'], [class*=\'map\']");
                maps.forEach(function(mapElement) {
                    // Tenta encontrar a instância do mapa
                    for (var key in window) {
                        if (window[key] && window[key].constructor && window[key].constructor.name === "Map") {
                            try {
                                if (window[key].getDiv && window[key].getDiv() === mapElement) {
                                    google.maps.event.trigger(window[key], "resize");
                                }
                            } catch(e) {}
                        }
                    }
                });
                
                // Dispara evento resize global
                google.maps.event.trigger(window, "resize");
            }
        }
        
        // Tenta redimensionar quando a página carregar
        if (document.readyState === "complete") {
            setTimeout(resizeMap, 100);
            setTimeout(resizeMap, 500);
            setTimeout(resizeMap, 1000);
        } else {
            window.addEventListener("load", function() {
                setTimeout(resizeMap, 100);
                setTimeout(resizeMap, 500);
                setTimeout(resizeMap, 1000);
            });
        }
        
        // Redimensiona quando a janela mudar de tamanho
        window.addEventListener("resize", resizeMap);
    })();
    </script>';
    
    // Insere o CSS e script no <head> ou antes do </body>
    // IMPORTANTE: O script da chave do Google Maps DEVE ser inserido PRIMEIRO, antes de qualquer script do Google Maps
    $allScripts = $googleMapsKeyScript . $mapStyles . $interceptorScript;
    
    // Tenta inserir no início do <head> primeiro (antes de qualquer script)
    if (stripos($content, '<head') !== false) {
        // Insere logo após a tag <head> ou <head ...>
        $content = preg_replace('/(<head[^>]*>)/i', '$1' . $googleMapsKeyScript, $content, 1);
        // Depois insere os outros scripts antes do </head>
        if (stripos($content, '</head>') !== false) {
            $content = str_ireplace('</head>', $mapStyles . $interceptorScript . '</head>', $content);
        }
    } elseif (stripos($content, '</head>') !== false) {
        // Se tem </head> mas não encontrou <head>, insere antes do </head>
        $content = str_ireplace('</head>', $allScripts . '</head>', $content);
    } elseif (stripos($content, '<body') !== false) {
        // Se não tem </head>, insere antes do <body>
        $content = preg_replace('/(<body[^>]*>)/i', $allScripts . '$1', $content, 1);
    } elseif (stripos($content, '</body>') !== false) {
        $content = str_ireplace('</body>', $allScripts . '</body>', $content);
    } else {
        // Se não tem </head> nem </body>, adiciona no início
        $content = $allScripts . $content;
    }
    
    // Ajusta caminhos relativos no HTML para apontar para o endpoint
    // Substitui referências a arquivos relativos (src, href) para usar o endpoint
    
    $content = preg_replace_callback(
        '/(src|href)=["\']([^"\']+)["\']/i',
        function($matches) use ($fileDir, $basePathReal) {
            $attr = $matches[1];
            $url = $matches[2];
            
            // Se já é uma URL absoluta (http/https) ou começa com /, não altera
            if (preg_match('/^(https?:|\/)/', $url)) {
                return $matches[0];
            }
            
            // Ignora URLs de data: ou javascript: ou mailto:
            if (preg_match('/^(data:|javascript:|mailto:)/i', $url)) {
                return $matches[0];
            }
            
            // Ignora se já começa com api/view_project.php (já foi processado)
            if (strpos($url, 'api/view_project.php') === 0) {
                return $matches[0];
            }
            
            // Ignora se começa com api/ mas não é view_project.php (pode ser um caminho incorreto)
            // Neste caso, tenta corrigir removendo o "api/" do início
            if (strpos($url, 'api/') === 0 && strpos($url, 'api/view_project.php') !== 0) {
                $url = substr($url, 4); // Remove "api/"
            }
            
            // Resolve caminho relativo em relação ao diretório do arquivo HTML
            // Remove ./ do início se existir
            $url = ltrim($url, './');
            
            // Constrói o caminho absoluto do recurso
            $resourcePath = $fileDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $url);
            
            // Normaliza o caminho (resolve .. e .)
            // Usa uma função customizada para normalizar sem verificar existência
            $normalizedPath = normalizePath($resourcePath);
            
            // Verifica se o caminho normalizado está dentro do BASE_PATH
            if ($basePathReal === false) {
                return $matches[0];
            }
            
            // Compara caminhos normalizados
            $basePathNormalized = normalizePath($basePathReal);
            $normalizedPathNormalized = normalizePath($normalizedPath);
            
            if (strpos($normalizedPathNormalized, $basePathNormalized) !== 0) {
                // Se não está dentro, mantém original (pode ser um recurso externo)
                return $matches[0];
            }
            
            // Calcula caminho relativo do recurso em relação ao BASE_PATH
            $resourceRelative = str_replace($basePathNormalized, '', $normalizedPathNormalized);
            $resourceRelative = str_replace('\\', '/', $resourceRelative);
            $resourceRelative = trim($resourceRelative, '/');
            
            // Se o caminho relativo está vazio, mantém original
            if (empty($resourceRelative)) {
                return $matches[0];
            }
            
            return $attr . '="api/view_project.php?path=' . urlencode($resourceRelative) . '"';
        },
        $content
    );
    
    // Define headers apropriados
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    
    echo $content;
}

/**
 * Serve um arquivo estático (imagem, CSS, JS, etc)
 * 
 * @param string $filePath Caminho absoluto do arquivo
 */
function serveStaticFile(string $filePath): void
{
    // Verifica se o arquivo existe
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Arquivo não encontrado'
        ]);
        return;
    }
    
    if (!is_readable($filePath)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Acesso negado ao arquivo'
        ]);
        return;
    }
    
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'html' => 'text/html',
        'xml' => 'application/xml',
    ];
    
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
    
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($filePath));
    header('X-Content-Type-Options: nosniff');
    
    // Cache para arquivos estáticos
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'css', 'js'])) {
        header('Cache-Control: public, max-age=31536000');
    }
    
    readfile($filePath);
}

// Processa requisição
try {
    // Valida parâmetro
    if (!isset($_GET['path']) || empty($_GET['path'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Parâmetro "path" é obrigatório'
        ]);
        exit;
    }
    
    $relativePath = $_GET['path'];
    $baseDir = $_GET['base'] ?? null;
    
    // Se baseDir foi fornecido, tenta resolver o caminho em relação a ele primeiro
    $absolutePath = null;
    if ($baseDir !== null && !empty($baseDir)) {
        $absolutePath = validateAndResolvePath($relativePath, $baseDir);
    }
    
    // Se não resolveu com baseDir, tenta resolver normalmente
    if ($absolutePath === null) {
        $absolutePath = validateAndResolvePath($relativePath);
    }
    
    if ($absolutePath === null) {
        http_response_code(404);
        
        // Debug temporário (remover em produção)
        $debugInfo = [];
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            $debugInfo['base_path'] = BASE_PATH;
            $debugInfo['base_path_real'] = realpath(BASE_PATH);
            $debugInfo['relative_path'] = $relativePath;
            $debugInfo['base_dir'] = $baseDir;
            
            // Tenta construir o caminho para debug
            $testPath = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $debugInfo['test_path'] = $testPath;
            $debugInfo['test_path_normalized'] = normalizePath($testPath);
            $debugInfo['test_path_exists'] = file_exists($testPath);
            $debugInfo['test_path_readable'] = is_readable($testPath);
        }
        
        echo json_encode([
            'success' => false,
            'error' => 'Arquivo não encontrado ou acesso negado',
            'debug' => $debugInfo
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Determina tipo de arquivo
    $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
    
    if ($extension === 'html') {
        serveHtmlFile($absolutePath);
    } else {
        serveStaticFile($absolutePath);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao processar requisição: ' . $e->getMessage()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao processar requisição'
        ]);
    }
    
    error_log('Erro em view_project.php: ' . $e->getMessage());
}

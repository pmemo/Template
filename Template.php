<?php
class Template
{
    private static $iconSrc = '';
    private static $file = '';
    private static $data = [];
    private static $title = '';
    private static $resources = [
        'css' => [],
        'js' => []
    ];
    
    private static $tags = [
        '\(\$([A-Za-z0-9_]+)\.([A-Za-z0-9_]+)\)' => '$$1["$2"]',
        '\$([A-Za-z0-9_]+)\.([A-Za-z0-9_]+)' => '$$1["$2"]',
        '\$([^.]*)\.\$([^\s]*)' => '$$1[$$2]',
        
        '@data ([A-Za-z0-9_]+)\.([A-Za-z0-9_]+)' => 'self::$data["$1"]["$2"]',
        '@data ([A-Za-z0-9_]+)' => 'self::$data["$1"]',

        '@foreach ([^\s].*) as ([^\r\n].*)' => '<?php foreach($1 as $2): ?>',
        '@endforeach' => '<?php endforeach; ?>',

        '\{# js #\}' => '<?php self::renderResource("js"); ?>',
        '\{# css #\}' => '<?php self::renderResource("css"); ?>',
        '\{# title #\}' => '<?php echo self::$title; ?>',

        '@if ([^\r\n]*)' => '<?php if($1): ?>',
        '@elseif ([^\r\n]*)' => '<?php elseif($1): ?>',
        '@else' => '<?php else: ?>',
        '@endif' => '<?php endif; ?>',

        '\{\{ ([^}]*) \}\}' => '<?php echo $1; ?>',
        '\{\{([^}]*)\}\}' => '<?php echo $1; ?>'
    ];

    private static function buildTemplate()
    {
        $tpl = self::loadTemplate(self::$file);
        return '
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link rel="icon" href="'.self::$iconSrc.'">
                <title>{# title #}</title>
                {# css #}
            </head>
            <body>
                '.$tpl.'
                {# js #}
            </body>
            </html>
        ';
    }

    private static function loadTemplate($templatePath)
    {
        $path = $templatePath;
        if (dirname($templatePath) != dirname(self::$file)) {
            $path = dirname(self::$file).'/'.$templatePath;
        }

        if (!file_exists($path)) {
            throw new Exception("Template Error: Template file not exists! ($path)");
        }

        $templateContent = file_get_contents($path);
        $templatePathName = explode('.', $path)[0];
        self::loadResources($templatePathName);

        return $templateContent;
    }

    private static function loadResources($resourcePath, $type = null)
    {
        foreach (self::$resources as $resourceType => $value) {
            if (isset($type) && $resourceType == $type || !isset($type)) {
                if (file_exists($resourcePath.".$resourceType")) {
                    array_push(self::$resources[$resourceType], $resourcePath . ".$resourceType");
                }
            }
        }
    }

    public static function resource($resourcePath)
    {
        if (file_exists($resourcePath)) {
            $type = explode('.', $resourcePath);
            $type = end($type);
            array_push(self::$resources[$type], $resourcePath);
        } else {
            throw new Exception('[Template::resource] ("'.$resourcePath.'") file not exists.');
        }
    }

    public static function favicon($src)
    {
        self::$iconSrc = $src;
    }

    private static function replaceTags($content)
    {
        $count = 0;
        foreach (self::$tags as $tag => $php) {
            $content = preg_replace('/'.$tag.'/', $php, $content, -1, $count);
        }
 
        if($count) {
            ob_start();
            eval("?>$content");
            $content = ob_get_contents();
            ob_end_clean();
        }

        return $content;
    }

    private static function nodeTemplateExists($tpl)
    {
        preg_match('/@tpl ([^\r\n]*)/', $tpl, $matches);
        return ($matches ? true : false);
    }

    public static function title($text)
    {
        self::$title = $text;
    }

    public static function render($file, $withLayout = true)
    {
        self::$file = $file;

        if (!file_exists(self::$file)) {
            throw new Exception('Template file not exists');
        }
        
        $content = '';

        if($withLayout) {
            $content = self::buildTemplate();
        } else {
            $content = file_get_contents($file);
        }

        while (self::nodeTemplateExists($content)) {
            $content = preg_replace('/@tpl ([^\r\n]*)/', '<?php echo self::loadTemplate(self::replaceTags("$1")); ?>', $content);
            ob_start();
            eval("?>$content");
            $content = ob_get_contents();
            ob_end_clean();
        }
        $content = self::replaceTags($content);
        echo $content;
    }

    public static function set($key, $value)
    {
        self::$data[$key] = $value;
    }

    private static function renderResource($key)
    {
        $regx = '';
        switch ($key) {
            case 'css':
            $regx = '<link rel="stylesheet" href="/$1" />';
            break;
            case 'js':
            $regx = '<script src="/$1"></script>';
            break;
        }

        foreach (self::$resources[$key] as $resource) {
            echo str_replace("$1", $resource, $regx);
        }
    }
}

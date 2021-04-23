<?php
class Template
{
    private static $extend;
    private static $blocks = [];
    private static $data = [];
    
    private static $tags = [
        '/\(\$([A-Za-z0-9_]+)\.([A-Za-z0-9_]+)\)/' => '$$1["$2"]',
        '/\$([A-Za-z0-9_]+)\.([A-Za-z0-9_]+)/' => '$$1["$2"]',
        '/\$([^.]*)\.\$([^\s]*)/' => '$$1[$$2]',
        
        '/@data ([A-Za-z0-9_]+)\.([A-Za-z0-9_]+)/' => 'self::$data["$1"]["$2"]',
        '/@data ([A-Za-z0-9_]+)/' => 'self::$data["$1"]',

        '/@extend ([^\r\n].*)/' => '<?php self::$extend = $1; ?>',
        
        '/@block ([^\n][A-Za-z0-9_]+)(.+?)@endblock/s' => '<?php self::handleBlock("$1", \'$2\'); ?>',

        '/@foreach ([^\s].*) as ([^\r\n].*)/' => '<?php foreach($1 as $2): ?>',
        '/@endforeach/' => '<?php endforeach; ?>',

        '/@if ([^\r\n]*)/' => '<?php if($1): ?>',
        '/@elseif ([^\r\n]*)/' => '<?php elseif($1): ?>',
        '/@else/' => '<?php else: ?>',
        '/@endif/' => '<?php endif; ?>',

        '/\{\{ ([^}]*) \}\}/' => '<?php echo $1; ?>',
        '/\{\{([^}]*)\}\}/' => '<?php echo $1; ?>'
    ];

    private static function handleBlock($name, $content) {
        if(isset(self::$blocks[$name])) {
            echo self::$blocks[$name];
        } else {
            if(self::$extend) {
                self::$blocks[$name] = self::replaceTags($content);
            } else {
                echo self::replaceTags($content);
            }
        }
    }

    private static function replaceTags($content, $debug = false)
    {
        $content = addcslashes($content, "'");
        foreach (self::$tags as $tag => $php) {
            $content = preg_replace($tag, $php, $content);
        }
 
        ob_start();
        eval("?>$content");
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    private static function nodeTemplateExists($tpl)
    {
        preg_match('/@include ([^\r\n]*)/', $tpl, $matches);
        return ($matches ? true : false);
    }

    private static function loadNodes($content) {
        while (self::nodeTemplateExists($content)) {
            $content = preg_replace('/@include ([^\r\n].*)/', '<?php echo self::loadTemplate($1); ?>', $content);
            ob_start();
            eval("?>$content");
            $content = ob_get_contents();
            ob_end_clean();
        }

        return $content;
    }

    private static function loadTemplate($path) {
        if (!file_exists($path)) {
            throw new Exception('Template file not exists.');
        }

        return file_get_contents($path);
    }

    public static function render($file, $data = null)
    {
        if($data) self::$data = array_merge($data, self::$data);

        $content = self::loadTemplate($file);
        $content = self::loadNodes($content);
        $content = self::replaceTags($content);

        if(self::$extend) {
            unset($content);
            $layout = self::loadTemplate(self::$extend);
            self::$extend = null;
            $layout = self::loadNodes($layout);
            $layout = self::replaceTags($layout);
            echo $layout;
        } else {
            echo $content;
        }
    }

    public static function set($key, $value)
    {
        self::$data[$key] = $value;
    }
}

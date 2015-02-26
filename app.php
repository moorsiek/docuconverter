<?

final class DocuConverterApp {
    static protected $opts = array(
        'f:',
        array(
            'format:'
        )
    );
    function run(){
        $options = self::getOpts();
        if (isset($options['format']) || isset($options['f'])) {
            $format = isset($options['format']) ? $options['format'] : $options['f']; 
        } else {
            $format = 'redmine';
        }
        self::log('Expecting format ' . $format);
        switch ($format) {
            case 'redmine':
                $converter = new RedmineDocuConverter();
                break;
            case 'githubmd':
                $converter = new GithubMdDocuConverter();
                break;
            default:
                throw new Exception('Unknown input format "' . $format .'"');
                break;
        }
        $result = $converter->convert();
        echo $result;
    }
    private function log($msg){
        fputs(STDERR, $msg . "\n");
    }
    private function getOpts(){
        $options = call_user_func_array('getopt', self::$opts);
        if ($options === false) {
            throw new Exception('Failed to parse options!');
        }
        return $options;
    } 
}
abstract class DocuConverter {
    protected function readFromStdin() {
        $fh = fopen('php://stdin', 'r');
        $src = '';
        while (false != ($s = fread($fh, 1024))) {
            $src .= $s;
        }
        fclose($fh);
        return $src;
    }
    abstract function convert();
}


class RedmineDocuConverter extends DocuConverter {
    protected $replacements = [
        [
            'h(\d+)\((#[^)]+)\)\.\s*([^\n\r]+)',
            'h$1. $3'
        ],
        [
            '\[\[([^|]+)\|([^\]]+)\]\]',
            '$2'
        ],
        [
            '\*(\*[^*]+\*)\*',
            '$1'
        ],
        [
            '\[([^|]+#[^|]+)\|([^\]]+)\]',
            '[$2|$1]'
        ],
        [
            '@([^@]+)@',
            '{{$1}}'
        ],

        [
            '<pre><code\s*class="([^"]+)"\s*>',
            '{code:$1|borderStyle=solid}'
        ],
        [
            '<pre><code\s*>',
            '{code:borderStyle=solid}'
        ],
        [
            '</code></pre>',
            '{code}'
        ]
    ]; 

    public function convert() {
        $src = $this->readFromStdin();
        foreach ($this->replacements as &$r) {
            $pat = preg_replace('!#!u', '\\#', $r[0]);
            $subst = $r[1];
            $src = preg_replace('#' . $pat . '#mui', $subst, $src);
        }
        return $src;
    }
}

class GithubMdDocuConverter extends DocuConverter {
    protected $replacements;
    
    function __construct(){
        $this->replacements = [
            [
                '#^(\#+)(?:(\s+)|$)#uim',
                function($m){
                    return 'h' . strlen($m[1]) . '.' . $m[2];
                }
            ],
            [
                '#\[([^\]]+)\]\(\#[^)]+\)#ui',
                '$1'
            ],
            [
                '#\*(\*[^*]+\*)\*#ui',
                '$1'
            ],
            [
                '#\[([^|]+\#[^|]+)\|([^\]]+)\]#ui',
                '[$2|$1]'
            ],
            [
                '#@([^@]+)@#ui',
                '{{$1}}'
            ],

            [
                '#```([^\n\r]+)(?:\n|\r|\n\r)([\s\S]*?)(?:\n|\r|\n\r)```#ui',
                '{code:$1|borderStyle=solid}
$2
{code}'
            ],
            [
                '#```(?:\n|\r|\n\r)([\s\S]*?)(?:\n|\r|\n\r)```#ui',
                '{code:borderStyle=solid}
$2
{code}'
            ],
            
            [
                '#<sub>([^<]+)</sub>#ui',
                ' ~$1~'
            ],
            [
                '#`([^`]+)`#ui',
                '{{$1}}'
            ],
            [
                '#>((?:[^\n\r]+  (?:\n|\r|\n\r))+[^\n\r]+)#ui',
                '{quote}$1{quote}'
            ],
            [
                '#>([^\n\r]+)#ui',
                'bq. $1'
            ]
        ];
    }

    public function convert() {
        $src = $this->readFromStdin();
        foreach ($this->replacements as &$r) {
//            $pat = preg_replace('!#!u', '\\#', $r[0]);
            $pat = $r[0];
            $subst = $r[1];
            if (is_callable($subst)) {
                $src = preg_replace_callback($pat, $subst, $src);
            } else {
                $src = preg_replace($pat, $subst, $src);
            }
        }
        return $src;
    }
}


(new DocuConverterApp())->run();




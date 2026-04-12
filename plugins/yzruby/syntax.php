<?php
/**
 * DokuWiki YzRuby Plugin
 *
 * Supports:
 * - {{yz|text}} and aliases with optional size: {{yzregular|text|big}}
 * - {{ruby|base|annotation}} (only if ruby plugin is disabled)
 * - %base|annotation% shorthand
 * - {{autoruby}} ... {{/autoruby}} table blocks
 */

if (!defined('DOKU_INC')) die();

class syntax_plugin_yzruby extends DokuWiki_Syntax_Plugin {

    public function getType() {
        return 'substition';
    }

    public function getSort() {
        return 149;
    }

    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern(
            '\\{\\{(?:yz(?:reg(?:ular)?|normal)?|yzbold|yzmodern|yzfuture|yzitalic|yzold|yzancient)\\|[^}\\n]*\\}\\}',
            $mode,
            'plugin_yzruby'
        );

        if (plugin_isdisabled('ruby')) {
            $this->Lexer->addSpecialPattern('\\{\\{ruby\\|[^}\\n]*\\}\\}', $mode, 'plugin_yzruby');
        }

        $this->Lexer->addSpecialPattern('%[^%\\n|][^%\\n]*\\|[^%\\n]+%', $mode, 'plugin_yzruby');
        $this->Lexer->addEntryPattern('\\{\\{autoruby\\}\\}(?=[\\s\\S]*\\{\\{\\/autoruby\\}\\})', $mode, 'plugin_yzruby');
        $this->Lexer->addExitPattern('\\{\\{\\/autoruby\\}\\}', 'plugin_yzruby');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler) {
        if ($match === '{{autoruby}}') {
            return array(
                'kind' => 'autoruby',
                'state' => $state,
                'text' => '',
            );
        }

        if ($match === '{{/autoruby}}') {
            return array(
                'kind' => 'autoruby',
                'state' => $state,
                'text' => '',
            );
        }

        if (substr($match, 0, 2) === '{{') {
            return $this->handleBracedSyntax($match);
        }

        if ($state === DOKU_LEXER_UNMATCHED) {
            return array(
                'kind' => 'autoruby',
                'state' => $state,
                'text' => $match,
            );
        }

        return $this->handlePercentSyntax($match);
    }

    protected function handleBracedSyntax($match) {
        $inner = substr($match, 2, -2);
        $parts = explode('|', $inner, 3);
        $tag = strtolower(trim($parts[0]));

        if ($tag === 'ruby') {
            $base = isset($parts[1]) ? trim($parts[1]) : '';
            $annotation = isset($parts[2]) ? trim($parts[2]) : '';
            return array(
                'kind' => 'ruby',
                'base' => $base,
                'annotation' => $annotation,
            );
        }

        $text = isset($parts[1]) ? trim($parts[1]) : '';
        $size = isset($parts[2]) ? trim($parts[2]) : 'normal';

        return array(
            'kind' => 'yiv',
            'variant' => $this->normalizeVariant($tag),
            'size' => $this->normalizeSize($size),
            'text' => $text,
        );
    }

    protected function handlePercentSyntax($match) {
        $inner = substr($match, 1, -1);
        $parts = explode('|', $inner, 2);

        return array(
            'kind' => 'ruby',
            'base' => trim($parts[0]),
            'annotation' => isset($parts[1]) ? trim($parts[1]) : '',
        );
    }

    protected function normalizeVariant($tag) {
        static $map = array(
            'yz' => 'regular',
            'yzreg' => 'regular',
            'yzregular' => 'regular',
            'yznormal' => 'regular',
            'yzbold' => 'modern',
            'yzmodern' => 'modern',
            'yzfuture' => 'modern',
            'yzitalic' => 'ancient',
            'yzold' => 'ancient',
            'yzancient' => 'ancient',
        );

        return isset($map[$tag]) ? $map[$tag] : 'regular';
    }

    protected function variantFontClass($variant) {
        static $map = array(
            'regular' => 'yiv-font',
            'modern' => 'yiv-bold',
            'ancient' => 'yiv-italic',
        );

        return isset($map[$variant]) ? $map[$variant] : 'yiv-font';
    }

    protected function normalizeSize($size) {
        static $map = array(
            'tiny' => 'tiny',
            'xs' => 'tiny',
            'small' => 'small',
            'sm' => 'small',
            'normal' => 'normal',
            'medium' => 'normal',
            'md' => 'normal',
            'big' => 'big',
            'large' => 'big',
            'lg' => 'big',
            'huge' => 'huge',
            'xl' => 'huge',
        );

        $size = strtolower(trim($size));
        return isset($map[$size]) ? $map[$size] : 'normal';
    }

    protected function getRubyParentheses() {
        $parentheses = $this->getConf('parentheses');
        if (utf8_strlen($parentheses) > 1) {
            return array(
                utf8_substr($parentheses, 0, 1),
                utf8_substr($parentheses, 1, 1),
            );
        }

        return array();
    }

    public function render($format, Doku_Renderer $renderer, $data) {
        if (!is_array($data) || !isset($data['kind'])) {
            return false;
        }

        $rp = $this->getRubyParentheses();
        $left = isset($rp[0]) ? $rp[0] : '';
        $right = isset($rp[1]) ? $rp[1] : '';

        if ($format === 'xhtml') {
            if ($data['kind'] === 'ruby') {
                $renderer->doc .= '<ruby class="yzruby-ann">';
                $renderer->doc .= '<rb>' . hsc($data['base']) . '</rb>';
                if ($left !== '') $renderer->doc .= '<rp>' . hsc($left) . '</rp>';
                $renderer->doc .= '<rt>' . hsc($data['annotation']) . '</rt>';
                if ($right !== '') $renderer->doc .= '<rp>' . hsc($right) . '</rp>';
                $renderer->doc .= '</ruby>';
                return true;
            }

            if ($data['kind'] === 'autoruby') {
                if ($data['state'] === DOKU_LEXER_ENTER || $data['state'] === DOKU_LEXER_EXIT) {
                    return true;
                }

                $renderer->doc .= $this->renderAutorubyTable($data['text']);
                return true;
            }

            $class = 'yzruby ' . $this->variantFontClass($data['variant']) . ' yzruby--' . $data['variant'] . ' yzruby-size-' . $data['size'];
            $renderer->doc .= '<span class="' . hsc($class) . '">' . hsc($data['text']) . '</span>';
            return true;
        }

        if ($format === 'metadata') {
            if (!$renderer->capture) return true;

            if ($data['kind'] === 'ruby') {
                $renderer->doc .= hsc($data['base']) . hsc($left) . hsc($data['annotation']) . hsc($right);
            } elseif ($data['kind'] === 'autoruby') {
                $renderer->doc .= trim(strip_tags($data['text']));
            } else {
                $renderer->doc .= hsc($data['text']);
            }
            return true;
        }

        return false;
    }

    protected function renderAutorubyTable($text) {
        $text = trim($text);
        if ($text === '') return '';

        $lines = preg_split('/\R/u', $text);
        $thead = array();
        $tbody = array();
        $headerDone = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if ($line === '{{autoruby}}' || $line === '{{/autoruby}}') continue;

            $isHeader = false;
            if (substr($line, 0, 1) === '^') {
                $isHeader = true;
                $line = trim($line, " \t^");
            }

            $cells = $this->splitAutorubyCells($line);
            if (!$cells) continue;

            $rowHtml = $this->renderAutorubyRow($cells, $isHeader);
            if ($rowHtml === '') continue;

            if ($isHeader && !$headerDone) {
                $thead[] = $rowHtml;
                $headerDone = true;
            } else {
                $tbody[] = $rowHtml;
            }
        }

        if (!$thead && !$tbody) {
            return '';
        }

        $out = '<table class="yzautoruby">';
        if ($thead) {
            $out .= '<thead>' . implode('', $thead) . '</thead>';
        }
        if ($tbody) {
            $out .= '<tbody>' . implode('', $tbody) . '</tbody>';
        }
        $out .= '</table>';
        return $out;
    }

    protected function splitAutorubyCells($line) {
        $line = trim($line);
        if ($line === '') return array();

        if (substr($line, 0, 1) === '|') {
            $line = trim($line, " \t|");
            $cells = preg_split('/\s*\|\s*/u', $line);
        } elseif (substr($line, 0, 1) === '^' || strpos($line, '^') !== false) {
            $line = trim($line, " \t^");
            $cells = preg_split('/\s*\^\s*/u', $line);
        } else {
            $cells = preg_split('/\s*\|\s*/u', $line);
        }
        if (!is_array($cells)) return array();

        $out = array();
        foreach ($cells as $cell) {
            $cell = trim($cell);
            if ($cell !== '') {
                $out[] = $cell;
            } else {
                $out[] = '';
            }
        }
        return $out;
    }

    protected function renderAutorubyRow(array $cells, $isHeader = false) {
        $tag = $isHeader ? 'th' : 'td';
        $count = count($cells);
        $cols = array();

        if ($count === 1) {
            $cols[] = '<' . $tag . ' colspan="3">' . hsc($cells[0]) . '</' . $tag . '>';
            return '<tr>' . implode('', $cols) . '</tr>';
        }

        $base = $cells[0];
        $reading = isset($cells[1]) ? $cells[1] : '';
        $meaning = isset($cells[2]) ? $cells[2] : '';

        if ($isHeader) {
            foreach ($cells as $cell) {
                $cols[] = '<' . $tag . '>' . hsc($cell) . '</' . $tag . '>';
            }
            return '<tr>' . implode('', $cols) . '</tr>';
        }

        $cols[] = '<td class="yzautoruby-base">' . $this->renderAutorubyPhrase($base, $reading) . '</td>';
        $cols[] = '<td class="yzautoruby-reading">' . hsc($reading) . '</td>';
        $cols[] = '<td class="yzautoruby-meaning">' . hsc($meaning) . '</td>';

        for ($i = 3; $i < $count; $i++) {
            $cols[] = '<td>' . hsc($cells[$i]) . '</td>';
        }

        return '<tr>' . implode('', $cols) . '</tr>';
    }

    protected function renderAutorubyPhrase($baseText, $readingText) {
        $baseWords = preg_split('/\s+/u', trim((string) $baseText), -1, PREG_SPLIT_NO_EMPTY);
        $readingWords = preg_split('/\s+/u', trim((string) $readingText), -1, PREG_SPLIT_NO_EMPTY);

        if (!$baseWords) {
            return '';
        }

        if (!$readingWords) {
            return hsc(trim((string) $baseText));
        }

        $limit = min(count($baseWords), count($readingWords));
        $parts = array();

        for ($i = 0; $i < $limit; $i++) {
            $parts[] = '<ruby class="yzruby-ann"><rb>' . hsc($baseWords[$i]) . '</rb><rp>(' . '</rp><rt>' . hsc($readingWords[$i]) . '</rt><rp>)</rp></ruby>';
        }

        if (count($baseWords) > $limit) {
            for ($i = $limit; $i < count($baseWords); $i++) {
                $parts[] = hsc($baseWords[$i]);
            }
        }

        return implode(' ', $parts);
    }
}

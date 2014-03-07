<?php

namespace Maki;

class Markdown extends \Michelf\MarkdownExtra
{
    public $baseUrl;

    public function __construct()
    {
        // doLink is 20, add base url just before
        $this->span_gamut['doBaseUrl'] = 19;

        parent::__construct();
    }

    public function doBaseUrl($text)
    {
        // URLs containing "://" are left untouched
        return preg_replace('~(?<!!)(\[.+?\]\()(?!\w++://)(?!#)(\S*(?:\s*+".+?")?\))~', '$1'.$this->baseUrl.'$2', $text);
    }
}
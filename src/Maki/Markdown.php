<?php

namespace Maki;

class Markdown extends \Michelf\MarkdownExtra
{
    public $baseUrl;
	protected $events = [];

    public function __construct()
    {
        // doLink is 20, add base url just before
        $this->span_gamut['doBaseUrl'] = 19;
        $this->span_gamut['doMakiEventToc'] = 20;
        $this->document_gamut['doMakiEvent'] = 4;
        $this->block_gamut['doMakiEvent'] = 4;

        parent::__construct();
    }

    public function doBaseUrl($text)
    {
        // URLs containing "://" are left untouched
        return preg_replace('~(?<!!)(\[.+?\]\()(?!\w++://)(?!#)(\S*(?:\s*+".+?")?\))~', '$1'.$this->baseUrl.'$2', $text);
    }

    public function doMakiEventToc($text)
    {
    	$table = '<div class="event-toc"><span class="event-toc-heading">Events table of contents:</span><ul class="event-toc-list">';
    	foreach ($this->events as $event) {
			$table .= '<li><a href="#event-'.$event.'">'.$event.'</a></li>';
	    }
	    $table .= '</ul></div>';

        return $this->hashPart(preg_replace('/(?:\n|\A)\[event-toc\]$/xm', $table, $text));
    }

	/**
	 * @param $text
	 * @return mixed
	 */
    public function doMakiEvent($text)
    {
    	$markdown = new Markdown();
    	$markdown->baseUrl = $this->baseUrl;

    	$me = $this;
    	return preg_replace_callback('/
	            (?:\n|\A)
	            # 1: Opening marker
	            ```event           
	            \s* \n # Whitespace and newline following marker.
	            # 4: Content
				(
					(?>
						(?!``` [ ]* \n)	# Not a closing marker.
						.*\n+
					)+
				)				
				# Closing marker.
				``` \s* (?= \n )
	        /xm',
		    function($matches) use ($me, $markdown) {
    		    $event = Spyc::YAMLLoadString($matches[1]);

    		    $html = "<div class='event'>";

			    if (isset($event['module'])) {
				    $html .= "<span class='event-module'><span class='event-module-label'>module</span><span class='event-module-name'>{$event['module']}</span></span>";
			    }

    		    if (isset($event['name'])) {
			    	$this->events[] = $event['name'];
			        $html .= "<span class='event-name'><span class='event-name-label'>event name</span><span class='event-name-name'>{$event['name']}</span><a name='event-{$event['name']}' class='event-name-link' href='#{$event['name']}'>#</a></span>";
		        }

		        if (isset($event['description'])) {
			    	$desc = $event['description'];
			    	// Allow description to be markdown.
			    	$desc = $markdown->transform($desc);
			    	$html .= "<span class='event-description'>{$desc}</span>";
		        }

		        if (isset($event['called'])) {
			    	$html .= "<div class='event-called'><span class='event-called-heading'>Called in</span><ul class='event-called-list'>";
			    	foreach ($event['called'] as $where) {
			    		$html .= "<li>{$where}</li>";
				    }
			    	$html .= "</ul></div>";
		        }

		        if (isset($event['arguments'])) {
		            $html .= "<div class='event-arguments'>
						<span class='event-arguments-heading'>Arguments</span>
						<table>
							<tr>
								<th class='event-argument-name'>Name</th>
								<th class='event-argument-type'>Type</th>
								<th class='event-argument-description'>Description</th>
							</tr>
							";

		            foreach ($event['arguments'] as $name => $arg) {
		                $html .= '<tr>';
		                $html .= '<td class="event-argument-name">'.$name.'</td>';
		                $html .= '<td class="event-argument-type">'.$arg['type'].'</td>';
		                $html .= '<td class="event-argument-description">'.$arg['description'].'</td>';
		                $html .= '</tr>';
		            }

			        $html .= "</table></div>";
		        }

    		    return $this->hashBlock($html);
		    }, $text);
    }
}
<?php

/**
 * This is compiled version of Maki script.
 * For proper source code go to http://emve.org/maki
 *
 * Compiled at: Tuesday 18th of October 2016 12:37:29 PM
 * Created by: Tomasz "ofca" Zeludziewicz <ofca@emve.org>
 */

namespace {
    define('MAKI_SINGLE_FILE', true);
}

// vendor/michelf/php-markdown/Michelf/MarkdownInterface.php


#
# Markdown  -  A text-to-HTML conversion tool for web writers
#
# PHP Markdown
# Copyright (c) 2004-2013 Michel Fortin
# <http://michelf.com/projects/php-markdown/>
#
# Original Markdown
# Copyright (c) 2004-2006 John Gruber
# <http://daringfireball.net/projects/markdown/>
#
namespace Michelf {


#
# Markdown Parser Interface
#

interface MarkdownInterface {

  #
  # Initialize the parser and return the result of its transform method.
  # This will work fine for derived classes too.
  #
  public static function defaultTransform($text);

  #
  # Main function. Performs some preprocessing on the input text
  # and pass it through the document gamut.
  #
  public function transform($text);

}




}




// vendor/michelf/php-markdown/Michelf/Markdown.php


#
# Markdown  -  A text-to-HTML conversion tool for web writers
#
# PHP Markdown  
# Copyright (c) 2004-2013 Michel Fortin  
# <http://michelf.com/projects/php-markdown/>
#
# Original Markdown  
# Copyright (c) 2004-2006 John Gruber  
# <http://daringfireball.net/projects/markdown/>
#
namespace Michelf {


#
# Markdown Parser Class
#

class Markdown implements MarkdownInterface {

	### Version ###

	const  MARKDOWNLIB_VERSION  =  "1.4.0";

	### Simple Function Interface ###

	public static function defaultTransform($text) {
	#
	# Initialize the parser and return the result of its transform method.
	# This will work fine for derived classes too.
	#
		# Take parser class on which this function was called.
		$parser_class = \get_called_class();

		# try to take parser from the static parser list
		static $parser_list;
		$parser =& $parser_list[$parser_class];

		# create the parser it not already set
		if (!$parser)
			$parser = new $parser_class;

		# Transform text using parser.
		return $parser->transform($text);
	}

	### Configuration Variables ###

	# Change to ">" for HTML output.
	public $empty_element_suffix = " />";
	public $tab_width = 4;
	
	# Change to `true` to disallow markup or entities.
	public $no_markup = false;
	public $no_entities = false;
	
	# Predefined urls and titles for reference links and images.
	public $predef_urls = array();
	public $predef_titles = array();


	### Parser Implementation ###

	# Regex to match balanced [brackets].
	# Needed to insert a maximum bracked depth while converting to PHP.
	protected $nested_brackets_depth = 6;
	protected $nested_brackets_re;
	
	protected $nested_url_parenthesis_depth = 4;
	protected $nested_url_parenthesis_re;

	# Table of hash values for escaped characters:
	protected $escape_chars = '\`*_{}[]()>#+-.!';
	protected $escape_chars_re;


	public function __construct() {
	#
	# Constructor function. Initialize appropriate member variables.
	#
		$this->_initDetab();
		$this->prepareItalicsAndBold();
	
		$this->nested_brackets_re = 
			str_repeat('(?>[^\[\]]+|\[', $this->nested_brackets_depth).
			str_repeat('\])*', $this->nested_brackets_depth);
	
		$this->nested_url_parenthesis_re = 
			str_repeat('(?>[^()\s]+|\(', $this->nested_url_parenthesis_depth).
			str_repeat('(?>\)))*', $this->nested_url_parenthesis_depth);
		
		$this->escape_chars_re = '['.preg_quote($this->escape_chars).']';
		
		# Sort document, block, and span gamut in ascendent priority order.
		asort($this->document_gamut);
		asort($this->block_gamut);
		asort($this->span_gamut);
	}


	# Internal hashes used during transformation.
	protected $urls = array();
	protected $titles = array();
	protected $html_hashes = array();
	
	# Status flag to avoid invalid nesting.
	protected $in_anchor = false;
	
	
	protected function setup() {
	#
	# Called before the transformation process starts to setup parser 
	# states.
	#
		# Clear global hashes.
		$this->urls = $this->predef_urls;
		$this->titles = $this->predef_titles;
		$this->html_hashes = array();
		
		$this->in_anchor = false;
	}
	
	protected function teardown() {
	#
	# Called after the transformation process to clear any variable 
	# which may be taking up memory unnecessarly.
	#
		$this->urls = array();
		$this->titles = array();
		$this->html_hashes = array();
	}


	public function transform($text) {
	#
	# Main function. Performs some preprocessing on the input text
	# and pass it through the document gamut.
	#
		$this->setup();
	
		# Remove UTF-8 BOM and marker character in input, if present.
		$text = preg_replace('{^\xEF\xBB\xBF|\x1A}', '', $text);

		# Standardize line endings:
		#   DOS to Unix and Mac to Unix
		$text = preg_replace('{\r\n?}', "\n", $text);

		# Make sure $text ends with a couple of newlines:
		$text .= "\n\n";

		# Convert all tabs to spaces.
		$text = $this->detab($text);

		# Turn block-level HTML blocks into hash entries
		$text = $this->hashHTMLBlocks($text);

		# Strip any lines consisting only of spaces and tabs.
		# This makes subsequent regexen easier to write, because we can
		# match consecutive blank lines with /\n+/ instead of something
		# contorted like /[ ]*\n+/ .
		$text = preg_replace('/^[ ]+$/m', '', $text);

		# Run document gamut methods.
		foreach ($this->document_gamut as $method => $priority) {
			$text = $this->$method($text);
		}
		
		$this->teardown();

		return $text . "\n";
	}
	
	protected $document_gamut = array(
		# Strip link definitions, store in hashes.
		"stripLinkDefinitions" => 20,
		
		"runBasicBlockGamut"   => 30,
		);


	protected function stripLinkDefinitions($text) {
	#
	# Strips link definitions from text, stores the URLs and titles in
	# hash references.
	#
		$less_than_tab = $this->tab_width - 1;

		# Link defs are in the form: ^[id]: url "optional title"
		$text = preg_replace_callback('{
							^[ ]{0,'.$less_than_tab.'}\[(.+)\][ ]?:	# id = $1
							  [ ]*
							  \n?				# maybe *one* newline
							  [ ]*
							(?:
							  <(.+?)>			# url = $2
							|
							  (\S+?)			# url = $3
							)
							  [ ]*
							  \n?				# maybe one newline
							  [ ]*
							(?:
								(?<=\s)			# lookbehind for whitespace
								["(]
								(.*?)			# title = $4
								[")]
								[ ]*
							)?	# title is optional
							(?:\n+|\Z)
			}xm',
			array(&$this, '_stripLinkDefinitions_callback'),
			$text);
		return $text;
	}
	protected function _stripLinkDefinitions_callback($matches) {
		$link_id = strtolower($matches[1]);
		$url = $matches[2] == '' ? $matches[3] : $matches[2];
		$this->urls[$link_id] = $url;
		$this->titles[$link_id] =& $matches[4];
		return ''; # String that will replace the block
	}


	protected function hashHTMLBlocks($text) {
		if ($this->no_markup)  return $text;

		$less_than_tab = $this->tab_width - 1;

		# Hashify HTML blocks:
		# We only want to do this for block-level HTML tags, such as headers,
		# lists, and tables. That's because we still want to wrap <p>s around
		# "paragraphs" that are wrapped in non-block-level tags, such as anchors,
		# phrase emphasis, and spans. The list of tags we're looking for is
		# hard-coded:
		#
		# *  List "a" is made of tags which can be both inline or block-level.
		#    These will be treated block-level when the start tag is alone on 
		#    its line, otherwise they're not matched here and will be taken as 
		#    inline later.
		# *  List "b" is made of tags which are always block-level;
		#
		$block_tags_a_re = 'ins|del';
		$block_tags_b_re = 'p|div|h[1-6]|blockquote|pre|table|dl|ol|ul|address|'.
						   'script|noscript|form|fieldset|iframe|math|svg|'.
						   'article|section|nav|aside|hgroup|header|footer|'.
						   'figure';

		# Regular expression for the content of a block tag.
		$nested_tags_level = 4;
		$attr = '
			(?>				# optional tag attributes
			  \s			# starts with whitespace
			  (?>
				[^>"/]+		# text outside quotes
			  |
				/+(?!>)		# slash not followed by ">"
			  |
				"[^"]*"		# text inside double quotes (tolerate ">")
			  |
				\'[^\']*\'	# text inside single quotes (tolerate ">")
			  )*
			)?	
			';
		$content =
			str_repeat('
				(?>
				  [^<]+			# content without tag
				|
				  <\2			# nested opening tag
					'.$attr.'	# attributes
					(?>
					  />
					|
					  >', $nested_tags_level).	# end of opening tag
					  '.*?'.					# last level nested tag content
			str_repeat('
					  </\2\s*>	# closing nested tag
					)
				  |				
					<(?!/\2\s*>	# other tags with a different name
				  )
				)*',
				$nested_tags_level);
		$content2 = str_replace('\2', '\3', $content);

		# First, look for nested blocks, e.g.:
		# 	<div>
		# 		<div>
		# 		tags for inner block must be indented.
		# 		</div>
		# 	</div>
		#
		# The outermost tags must start at the left margin for this to match, and
		# the inner nested divs must be indented.
		# We need to do this before the next, more liberal match, because the next
		# match will start at the first `<div>` and stop at the first `</div>`.
		$text = preg_replace_callback('{(?>
			(?>
				(?<=\n\n)		# Starting after a blank line
				|				# or
				\A\n?			# the beginning of the doc
			)
			(						# save in $1

			  # Match from `\n<tag>` to `</tag>\n`, handling nested tags 
			  # in between.
					
						[ ]{0,'.$less_than_tab.'}
						<('.$block_tags_b_re.')# start tag = $2
						'.$attr.'>			# attributes followed by > and \n
						'.$content.'		# content, support nesting
						</\2>				# the matching end tag
						[ ]*				# trailing spaces/tabs
						(?=\n+|\Z)	# followed by a newline or end of document

			| # Special version for tags of group a.

						[ ]{0,'.$less_than_tab.'}
						<('.$block_tags_a_re.')# start tag = $3
						'.$attr.'>[ ]*\n	# attributes followed by >
						'.$content2.'		# content, support nesting
						</\3>				# the matching end tag
						[ ]*				# trailing spaces/tabs
						(?=\n+|\Z)	# followed by a newline or end of document
					
			| # Special case just for <hr />. It was easier to make a special 
			  # case than to make the other regex more complicated.
			
						[ ]{0,'.$less_than_tab.'}
						<(hr)				# start tag = $2
						'.$attr.'			# attributes
						/?>					# the matching end tag
						[ ]*
						(?=\n{2,}|\Z)		# followed by a blank line or end of document
			
			| # Special case for standalone HTML comments:
			
					[ ]{0,'.$less_than_tab.'}
					(?s:
						<!-- .*? -->
					)
					[ ]*
					(?=\n{2,}|\Z)		# followed by a blank line or end of document
			
			| # PHP and ASP-style processor instructions (<? and <%)
			
					[ ]{0,'.$less_than_tab.'}
					(?s:
						<([?%])			# $2
						.*?
						\2>
					)
					[ ]*
					(?=\n{2,}|\Z)		# followed by a blank line or end of document
					
			)
			)}Sxmi',
			array(&$this, '_hashHTMLBlocks_callback'),
			$text);

		return $text;
	}
	protected function _hashHTMLBlocks_callback($matches) {
		$text = $matches[1];
		$key  = $this->hashBlock($text);
		return "\n\n$key\n\n";
	}
	
	
	protected function hashPart($text, $boundary = 'X') {
	#
	# Called whenever a tag must be hashed when a function insert an atomic 
	# element in the text stream. Passing $text to through this function gives
	# a unique text-token which will be reverted back when calling unhash.
	#
	# The $boundary argument specify what character should be used to surround
	# the token. By convension, "B" is used for block elements that needs not
	# to be wrapped into paragraph tags at the end, ":" is used for elements
	# that are word separators and "X" is used in the general case.
	#
		# Swap back any tag hash found in $text so we do not have to `unhash`
		# multiple times at the end.
		$text = $this->unhash($text);
		
		# Then hash the block.
		static $i = 0;
		$key = "$boundary\x1A" . ++$i . $boundary;
		$this->html_hashes[$key] = $text;
		return $key; # String that will replace the tag.
	}


	protected function hashBlock($text) {
	#
	# Shortcut function for hashPart with block-level boundaries.
	#
		return $this->hashPart($text, 'B');
	}


	protected $block_gamut = array(
	#
	# These are all the transformations that form block-level
	# tags like paragraphs, headers, and list items.
	#
		"doHeaders"         => 10,
		"doHorizontalRules" => 20,
		
		"doLists"           => 40,
		"doCodeBlocks"      => 50,
		"doBlockQuotes"     => 60,
		);

	protected function runBlockGamut($text) {
	#
	# Run block gamut tranformations.
	#
		# We need to escape raw HTML in Markdown source before doing anything 
		# else. This need to be done for each block, and not only at the 
		# begining in the Markdown function since hashed blocks can be part of
		# list items and could have been indented. Indented blocks would have 
		# been seen as a code block in a previous pass of hashHTMLBlocks.
		$text = $this->hashHTMLBlocks($text);
		
		return $this->runBasicBlockGamut($text);
	}
	
	protected function runBasicBlockGamut($text) {
	#
	# Run block gamut tranformations, without hashing HTML blocks. This is 
	# useful when HTML blocks are known to be already hashed, like in the first
	# whole-document pass.
	#
		foreach ($this->block_gamut as $method => $priority) {
			$text = $this->$method($text);
		}
		
		# Finally form paragraph and restore hashed blocks.
		$text = $this->formParagraphs($text);

		return $text;
	}
	
	
	protected function doHorizontalRules($text) {
		# Do Horizontal Rules:
		return preg_replace(
			'{
				^[ ]{0,3}	# Leading space
				([-*_])		# $1: First marker
				(?>			# Repeated marker group
					[ ]{0,2}	# Zero, one, or two spaces.
					\1			# Marker character
				){2,}		# Group repeated at least twice
				[ ]*		# Tailing spaces
				$			# End of line.
			}mx',
			"\n".$this->hashBlock("<hr$this->empty_element_suffix")."\n", 
			$text);
	}


	protected $span_gamut = array(
	#
	# These are all the transformations that occur *within* block-level
	# tags like paragraphs, headers, and list items.
	#
		# Process character escapes, code spans, and inline HTML
		# in one shot.
		"parseSpan"           => -30,

		# Process anchor and image tags. Images must come first,
		# because ![foo][f] looks like an anchor.
		"doImages"            =>  10,
		"doAnchors"           =>  20,
		
		# Make links out of things like `<http://example.com/>`
		# Must come after doAnchors, because you can use < and >
		# delimiters in inline links like [this](<url>).
		"doAutoLinks"         =>  30,
		"encodeAmpsAndAngles" =>  40,

		"doItalicsAndBold"    =>  50,
		"doHardBreaks"        =>  60,
		);

	protected function runSpanGamut($text) {
	#
	# Run span gamut tranformations.
	#
		foreach ($this->span_gamut as $method => $priority) {
			$text = $this->$method($text);
		}

		return $text;
	}
	
	
	protected function doHardBreaks($text) {
		# Do hard breaks:
		return preg_replace_callback('/ {2,}\n/', 
			array(&$this, '_doHardBreaks_callback'), $text);
	}
	protected function _doHardBreaks_callback($matches) {
		return $this->hashPart("<br$this->empty_element_suffix\n");
	}


	protected function doAnchors($text) {
	#
	# Turn Markdown link shortcuts into XHTML <a> tags.
	#
		if ($this->in_anchor) return $text;
		$this->in_anchor = true;
		
		#
		# First, handle reference-style links: [link text] [id]
		#
		$text = preg_replace_callback('{
			(					# wrap whole match in $1
			  \[
				('.$this->nested_brackets_re.')	# link text = $2
			  \]

			  [ ]?				# one optional space
			  (?:\n[ ]*)?		# one optional newline followed by spaces

			  \[
				(.*?)		# id = $3
			  \]
			)
			}xs',
			array(&$this, '_doAnchors_reference_callback'), $text);

		#
		# Next, inline-style links: [link text](url "optional title")
		#
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  \[
				('.$this->nested_brackets_re.')	# link text = $2
			  \]
			  \(			# literal paren
				[ \n]*
				(?:
					<(.+?)>	# href = $3
				|
					('.$this->nested_url_parenthesis_re.')	# href = $4
				)
				[ \n]*
				(			# $5
				  ([\'"])	# quote char = $6
				  (.*?)		# Title = $7
				  \6		# matching quote
				  [ \n]*	# ignore any spaces/tabs between closing quote and )
				)?			# title is optional
			  \)
			)
			}xs',
			array(&$this, '_doAnchors_inline_callback'), $text);

		#
		# Last, handle reference-style shortcuts: [link text]
		# These must come last in case you've also got [link text][1]
		# or [link text](/foo)
		#
		$text = preg_replace_callback('{
			(					# wrap whole match in $1
			  \[
				([^\[\]]+)		# link text = $2; can\'t contain [ or ]
			  \]
			)
			}xs',
			array(&$this, '_doAnchors_reference_callback'), $text);

		$this->in_anchor = false;
		return $text;
	}
	protected function _doAnchors_reference_callback($matches) {
		$whole_match =  $matches[1];
		$link_text   =  $matches[2];
		$link_id     =& $matches[3];

		if ($link_id == "") {
			# for shortcut links like [this][] or [this].
			$link_id = $link_text;
		}
		
		# lower-case and turn embedded newlines into spaces
		$link_id = strtolower($link_id);
		$link_id = preg_replace('{[ ]?\n}', ' ', $link_id);

		if (isset($this->urls[$link_id])) {
			$url = $this->urls[$link_id];
			$url = $this->encodeAttribute($url);
			
			$result = "<a href=\"$url\"";
			if ( isset( $this->titles[$link_id] ) ) {
				$title = $this->titles[$link_id];
				$title = $this->encodeAttribute($title);
				$result .=  " title=\"$title\"";
			}
		
			$link_text = $this->runSpanGamut($link_text);
			$result .= ">$link_text</a>";
			$result = $this->hashPart($result);
		}
		else {
			$result = $whole_match;
		}
		return $result;
	}
	protected function _doAnchors_inline_callback($matches) {
		$whole_match	=  $matches[1];
		$link_text		=  $this->runSpanGamut($matches[2]);
		$url			=  $matches[3] == '' ? $matches[4] : $matches[3];
		$title			=& $matches[7];

		$url = $this->encodeAttribute($url);

		$result = "<a href=\"$url\"";
		if (isset($title)) {
			$title = $this->encodeAttribute($title);
			$result .=  " title=\"$title\"";
		}
		
		$link_text = $this->runSpanGamut($link_text);
		$result .= ">$link_text</a>";

		return $this->hashPart($result);
	}


	protected function doImages($text) {
	#
	# Turn Markdown image shortcuts into <img> tags.
	#
		#
		# First, handle reference-style labeled images: ![alt text][id]
		#
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  !\[
				('.$this->nested_brackets_re.')		# alt text = $2
			  \]

			  [ ]?				# one optional space
			  (?:\n[ ]*)?		# one optional newline followed by spaces

			  \[
				(.*?)		# id = $3
			  \]

			)
			}xs', 
			array(&$this, '_doImages_reference_callback'), $text);

		#
		# Next, handle inline images:  ![alt text](url "optional title")
		# Don't forget: encode * and _
		#
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  !\[
				('.$this->nested_brackets_re.')		# alt text = $2
			  \]
			  \s?			# One optional whitespace character
			  \(			# literal paren
				[ \n]*
				(?:
					<(\S*)>	# src url = $3
				|
					('.$this->nested_url_parenthesis_re.')	# src url = $4
				)
				[ \n]*
				(			# $5
				  ([\'"])	# quote char = $6
				  (.*?)		# title = $7
				  \6		# matching quote
				  [ \n]*
				)?			# title is optional
			  \)
			)
			}xs',
			array(&$this, '_doImages_inline_callback'), $text);

		return $text;
	}
	protected function _doImages_reference_callback($matches) {
		$whole_match = $matches[1];
		$alt_text    = $matches[2];
		$link_id     = strtolower($matches[3]);

		if ($link_id == "") {
			$link_id = strtolower($alt_text); # for shortcut links like ![this][].
		}

		$alt_text = $this->encodeAttribute($alt_text);
		if (isset($this->urls[$link_id])) {
			$url = $this->encodeAttribute($this->urls[$link_id]);
			$result = "<img src=\"$url\" alt=\"$alt_text\"";
			if (isset($this->titles[$link_id])) {
				$title = $this->titles[$link_id];
				$title = $this->encodeAttribute($title);
				$result .=  " title=\"$title\"";
			}
			$result .= $this->empty_element_suffix;
			$result = $this->hashPart($result);
		}
		else {
			# If there's no such link ID, leave intact:
			$result = $whole_match;
		}

		return $result;
	}
	protected function _doImages_inline_callback($matches) {
		$whole_match	= $matches[1];
		$alt_text		= $matches[2];
		$url			= $matches[3] == '' ? $matches[4] : $matches[3];
		$title			=& $matches[7];

		$alt_text = $this->encodeAttribute($alt_text);
		$url = $this->encodeAttribute($url);
		$result = "<img src=\"$url\" alt=\"$alt_text\"";
		if (isset($title)) {
			$title = $this->encodeAttribute($title);
			$result .=  " title=\"$title\""; # $title already quoted
		}
		$result .= $this->empty_element_suffix;

		return $this->hashPart($result);
	}


	protected function doHeaders($text) {
		# Setext-style headers:
		#	  Header 1
		#	  ========
		#  
		#	  Header 2
		#	  --------
		#
		$text = preg_replace_callback('{ ^(.+?)[ ]*\n(=+|-+)[ ]*\n+ }mx',
			array(&$this, '_doHeaders_callback_setext'), $text);

		# atx-style headers:
		#	# Header 1
		#	## Header 2
		#	## Header 2 with closing hashes ##
		#	...
		#	###### Header 6
		#
		$text = preg_replace_callback('{
				^(\#{1,6})	# $1 = string of #\'s
				[ ]*
				(.+?)		# $2 = Header text
				[ ]*
				\#*			# optional closing #\'s (not counted)
				\n+
			}xm',
			array(&$this, '_doHeaders_callback_atx'), $text);

		return $text;
	}
	protected function _doHeaders_callback_setext($matches) {
		# Terrible hack to check we haven't found an empty list item.
		if ($matches[2] == '-' && preg_match('{^-(?: |$)}', $matches[1]))
			return $matches[0];
		
		$level = $matches[2]{0} == '=' ? 1 : 2;
		$block = "<h$level>".$this->runSpanGamut($matches[1])."</h$level>";
		return "\n" . $this->hashBlock($block) . "\n\n";
	}
	protected function _doHeaders_callback_atx($matches) {
		$level = strlen($matches[1]);
		$block = "<h$level>".$this->runSpanGamut($matches[2])."</h$level>";
		return "\n" . $this->hashBlock($block) . "\n\n";
	}


	protected function doLists($text) {
	#
	# Form HTML ordered (numbered) and unordered (bulleted) lists.
	#
		$less_than_tab = $this->tab_width - 1;

		# Re-usable patterns to match list item bullets and number markers:
		$marker_ul_re  = '[*+-]';
		$marker_ol_re  = '\d+[\.]';
		$marker_any_re = "(?:$marker_ul_re|$marker_ol_re)";

		$markers_relist = array(
			$marker_ul_re => $marker_ol_re,
			$marker_ol_re => $marker_ul_re,
			);

		foreach ($markers_relist as $marker_re => $other_marker_re) {
			# Re-usable pattern to match any entirel ul or ol list:
			$whole_list_re = '
				(								# $1 = whole list
				  (								# $2
					([ ]{0,'.$less_than_tab.'})	# $3 = number of spaces
					('.$marker_re.')			# $4 = first list item marker
					[ ]+
				  )
				  (?s:.+?)
				  (								# $5
					  \z
					|
					  \n{2,}
					  (?=\S)
					  (?!						# Negative lookahead for another list item marker
						[ ]*
						'.$marker_re.'[ ]+
					  )
					|
					  (?=						# Lookahead for another kind of list
					    \n
						\3						# Must have the same indentation
						'.$other_marker_re.'[ ]+
					  )
				  )
				)
			'; // mx
			
			# We use a different prefix before nested lists than top-level lists.
			# See extended comment in _ProcessListItems().
		
			if ($this->list_level) {
				$text = preg_replace_callback('{
						^
						'.$whole_list_re.'
					}mx',
					array(&$this, '_doLists_callback'), $text);
			}
			else {
				$text = preg_replace_callback('{
						(?:(?<=\n)\n|\A\n?) # Must eat the newline
						'.$whole_list_re.'
					}mx',
					array(&$this, '_doLists_callback'), $text);
			}
		}

		return $text;
	}
	protected function _doLists_callback($matches) {
		# Re-usable patterns to match list item bullets and number markers:
		$marker_ul_re  = '[*+-]';
		$marker_ol_re  = '\d+[\.]';
		$marker_any_re = "(?:$marker_ul_re|$marker_ol_re)";
		
		$list = $matches[1];
		$list_type = preg_match("/$marker_ul_re/", $matches[4]) ? "ul" : "ol";
		
		$marker_any_re = ( $list_type == "ul" ? $marker_ul_re : $marker_ol_re );
		
		$list .= "\n";
		$result = $this->processListItems($list, $marker_any_re);
		
		$result = $this->hashBlock("<$list_type>\n" . $result . "</$list_type>");
		return "\n". $result ."\n\n";
	}

	protected $list_level = 0;

	protected function processListItems($list_str, $marker_any_re) {
	#
	#	Process the contents of a single ordered or unordered list, splitting it
	#	into individual list items.
	#
		# The $this->list_level global keeps track of when we're inside a list.
		# Each time we enter a list, we increment it; when we leave a list,
		# we decrement. If it's zero, we're not in a list anymore.
		#
		# We do this because when we're not inside a list, we want to treat
		# something like this:
		#
		#		I recommend upgrading to version
		#		8. Oops, now this line is treated
		#		as a sub-list.
		#
		# As a single paragraph, despite the fact that the second line starts
		# with a digit-period-space sequence.
		#
		# Whereas when we're inside a list (or sub-list), that line will be
		# treated as the start of a sub-list. What a kludge, huh? This is
		# an aspect of Markdown's syntax that's hard to parse perfectly
		# without resorting to mind-reading. Perhaps the solution is to
		# change the syntax rules such that sub-lists must start with a
		# starting cardinal number; e.g. "1." or "a.".
		
		$this->list_level++;

		# trim trailing blank lines:
		$list_str = preg_replace("/\n{2,}\\z/", "\n", $list_str);

		$list_str = preg_replace_callback('{
			(\n)?							# leading line = $1
			(^[ ]*)							# leading whitespace = $2
			('.$marker_any_re.'				# list marker and space = $3
				(?:[ ]+|(?=\n))	# space only required if item is not empty
			)
			((?s:.*?))						# list item text   = $4
			(?:(\n+(?=\n))|\n)				# tailing blank line = $5
			(?= \n* (\z | \2 ('.$marker_any_re.') (?:[ ]+|(?=\n))))
			}xm',
			array(&$this, '_processListItems_callback'), $list_str);

		$this->list_level--;
		return $list_str;
	}
	protected function _processListItems_callback($matches) {
		$item = $matches[4];
		$leading_line =& $matches[1];
		$leading_space =& $matches[2];
		$marker_space = $matches[3];
		$tailing_blank_line =& $matches[5];

		if ($leading_line || $tailing_blank_line || 
			preg_match('/\n{2,}/', $item))
		{
			# Replace marker with the appropriate whitespace indentation
			$item = $leading_space . str_repeat(' ', strlen($marker_space)) . $item;
			$item = $this->runBlockGamut($this->outdent($item)."\n");
		}
		else {
			# Recursion for sub-lists:
			$item = $this->doLists($this->outdent($item));
			$item = preg_replace('/\n+$/', '', $item);
			$item = $this->runSpanGamut($item);
		}

		return "<li>" . $item . "</li>\n";
	}


	protected function doCodeBlocks($text) {
	#
	#	Process Markdown `<pre><code>` blocks.
	#
		$text = preg_replace_callback('{
				(?:\n\n|\A\n?)
				(	            # $1 = the code block -- one or more lines, starting with a space/tab
				  (?>
					[ ]{'.$this->tab_width.'}  # Lines must start with a tab or a tab-width of spaces
					.*\n+
				  )+
				)
				((?=^[ ]{0,'.$this->tab_width.'}\S)|\Z)	# Lookahead for non-space at line-start, or end of doc
			}xm',
			array(&$this, '_doCodeBlocks_callback'), $text);

		return $text;
	}
	protected function _doCodeBlocks_callback($matches) {
		$codeblock = $matches[1];

		$codeblock = $this->outdent($codeblock);
		$codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES);

		# trim leading newlines and trailing newlines
		$codeblock = preg_replace('/\A\n+|\n+\z/', '', $codeblock);

		$codeblock = "<pre><code>$codeblock\n</code></pre>";
		return "\n\n".$this->hashBlock($codeblock)."\n\n";
	}


	protected function makeCodeSpan($code) {
	#
	# Create a code span markup for $code. Called from handleSpanToken.
	#
		$code = htmlspecialchars(trim($code), ENT_NOQUOTES);
		return $this->hashPart("<code>$code</code>");
	}


	protected $em_relist = array(
		''  => '(?:(?<!\*)\*(?!\*)|(?<!_)_(?!_))(?=\S|$)(?![\.,:;]\s)',
		'*' => '(?<=\S|^)(?<!\*)\*(?!\*)',
		'_' => '(?<=\S|^)(?<!_)_(?!_)',
		);
	protected $strong_relist = array(
		''   => '(?:(?<!\*)\*\*(?!\*)|(?<!_)__(?!_))(?=\S|$)(?![\.,:;]\s)',
		'**' => '(?<=\S|^)(?<!\*)\*\*(?!\*)',
		'__' => '(?<=\S|^)(?<!_)__(?!_)',
		);
	protected $em_strong_relist = array(
		''    => '(?:(?<!\*)\*\*\*(?!\*)|(?<!_)___(?!_))(?=\S|$)(?![\.,:;]\s)',
		'***' => '(?<=\S|^)(?<!\*)\*\*\*(?!\*)',
		'___' => '(?<=\S|^)(?<!_)___(?!_)',
		);
	protected $em_strong_prepared_relist;
	
	protected function prepareItalicsAndBold() {
	#
	# Prepare regular expressions for searching emphasis tokens in any
	# context.
	#
		foreach ($this->em_relist as $em => $em_re) {
			foreach ($this->strong_relist as $strong => $strong_re) {
				# Construct list of allowed token expressions.
				$token_relist = array();
				if (isset($this->em_strong_relist["$em$strong"])) {
					$token_relist[] = $this->em_strong_relist["$em$strong"];
				}
				$token_relist[] = $em_re;
				$token_relist[] = $strong_re;
				
				# Construct master expression from list.
				$token_re = '{('. implode('|', $token_relist) .')}';
				$this->em_strong_prepared_relist["$em$strong"] = $token_re;
			}
		}
	}
	
	protected function doItalicsAndBold($text) {
		$token_stack = array('');
		$text_stack = array('');
		$em = '';
		$strong = '';
		$tree_char_em = false;
		
		while (1) {
			#
			# Get prepared regular expression for seraching emphasis tokens
			# in current context.
			#
			$token_re = $this->em_strong_prepared_relist["$em$strong"];
			
			#
			# Each loop iteration search for the next emphasis token. 
			# Each token is then passed to handleSpanToken.
			#
			$parts = preg_split($token_re, $text, 2, PREG_SPLIT_DELIM_CAPTURE);
			$text_stack[0] .= $parts[0];
			$token =& $parts[1];
			$text =& $parts[2];
			
			if (empty($token)) {
				# Reached end of text span: empty stack without emitting.
				# any more emphasis.
				while ($token_stack[0]) {
					$text_stack[1] .= array_shift($token_stack);
					$text_stack[0] .= array_shift($text_stack);
				}
				break;
			}
			
			$token_len = strlen($token);
			if ($tree_char_em) {
				# Reached closing marker while inside a three-char emphasis.
				if ($token_len == 3) {
					# Three-char closing marker, close em and strong.
					array_shift($token_stack);
					$span = array_shift($text_stack);
					$span = $this->runSpanGamut($span);
					$span = "<strong><em>$span</em></strong>";
					$text_stack[0] .= $this->hashPart($span);
					$em = '';
					$strong = '';
				} else {
					# Other closing marker: close one em or strong and
					# change current token state to match the other
					$token_stack[0] = str_repeat($token{0}, 3-$token_len);
					$tag = $token_len == 2 ? "strong" : "em";
					$span = $text_stack[0];
					$span = $this->runSpanGamut($span);
					$span = "<$tag>$span</$tag>";
					$text_stack[0] = $this->hashPart($span);
					$$tag = ''; # $$tag stands for $em or $strong
				}
				$tree_char_em = false;
			} else if ($token_len == 3) {
				if ($em) {
					# Reached closing marker for both em and strong.
					# Closing strong marker:
					for ($i = 0; $i < 2; ++$i) {
						$shifted_token = array_shift($token_stack);
						$tag = strlen($shifted_token) == 2 ? "strong" : "em";
						$span = array_shift($text_stack);
						$span = $this->runSpanGamut($span);
						$span = "<$tag>$span</$tag>";
						$text_stack[0] .= $this->hashPart($span);
						$$tag = ''; # $$tag stands for $em or $strong
					}
				} else {
					# Reached opening three-char emphasis marker. Push on token 
					# stack; will be handled by the special condition above.
					$em = $token{0};
					$strong = "$em$em";
					array_unshift($token_stack, $token);
					array_unshift($text_stack, '');
					$tree_char_em = true;
				}
			} else if ($token_len == 2) {
				if ($strong) {
					# Unwind any dangling emphasis marker:
					if (strlen($token_stack[0]) == 1) {
						$text_stack[1] .= array_shift($token_stack);
						$text_stack[0] .= array_shift($text_stack);
					}
					# Closing strong marker:
					array_shift($token_stack);
					$span = array_shift($text_stack);
					$span = $this->runSpanGamut($span);
					$span = "<strong>$span</strong>";
					$text_stack[0] .= $this->hashPart($span);
					$strong = '';
				} else {
					array_unshift($token_stack, $token);
					array_unshift($text_stack, '');
					$strong = $token;
				}
			} else {
				# Here $token_len == 1
				if ($em) {
					if (strlen($token_stack[0]) == 1) {
						# Closing emphasis marker:
						array_shift($token_stack);
						$span = array_shift($text_stack);
						$span = $this->runSpanGamut($span);
						$span = "<em>$span</em>";
						$text_stack[0] .= $this->hashPart($span);
						$em = '';
					} else {
						$text_stack[0] .= $token;
					}
				} else {
					array_unshift($token_stack, $token);
					array_unshift($text_stack, '');
					$em = $token;
				}
			}
		}
		return $text_stack[0];
	}


	protected function doBlockQuotes($text) {
		$text = preg_replace_callback('/
			  (								# Wrap whole match in $1
				(?>
				  ^[ ]*>[ ]?			# ">" at the start of a line
					.+\n					# rest of the first line
				  (.+\n)*					# subsequent consecutive lines
				  \n*						# blanks
				)+
			  )
			/xm',
			array(&$this, '_doBlockQuotes_callback'), $text);

		return $text;
	}
	protected function _doBlockQuotes_callback($matches) {
		$bq = $matches[1];
		# trim one level of quoting - trim whitespace-only lines
		$bq = preg_replace('/^[ ]*>[ ]?|^[ ]+$/m', '', $bq);
		$bq = $this->runBlockGamut($bq);		# recurse

		$bq = preg_replace('/^/m', "  ", $bq);
		# These leading spaces cause problem with <pre> content, 
		# so we need to fix that:
		$bq = preg_replace_callback('{(\s*<pre>.+?</pre>)}sx', 
			array(&$this, '_doBlockQuotes_callback2'), $bq);

		return "\n". $this->hashBlock("<blockquote>\n$bq\n</blockquote>")."\n\n";
	}
	protected function _doBlockQuotes_callback2($matches) {
		$pre = $matches[1];
		$pre = preg_replace('/^  /m', '', $pre);
		return $pre;
	}


	protected function formParagraphs($text) {
	#
	#	Params:
	#		$text - string to process with html <p> tags
	#
		# Strip leading and trailing lines:
		$text = preg_replace('/\A\n+|\n+\z/', '', $text);

		$grafs = preg_split('/\n{2,}/', $text, -1, PREG_SPLIT_NO_EMPTY);

		#
		# Wrap <p> tags and unhashify HTML blocks
		#
		foreach ($grafs as $key => $value) {
			if (!preg_match('/^B\x1A[0-9]+B$/', $value)) {
				# Is a paragraph.
				$value = $this->runSpanGamut($value);
				$value = preg_replace('/^([ ]*)/', "<p>", $value);
				$value .= "</p>";
				$grafs[$key] = $this->unhash($value);
			}
			else {
				# Is a block.
				# Modify elements of @grafs in-place...
				$graf = $value;
				$block = $this->html_hashes[$graf];
				$graf = $block;
//				if (preg_match('{
//					\A
//					(							# $1 = <div> tag
//					  <div  \s+
//					  [^>]*
//					  \b
//					  markdown\s*=\s*  ([\'"])	#	$2 = attr quote char
//					  1
//					  \2
//					  [^>]*
//					  >
//					)
//					(							# $3 = contents
//					.*
//					)
//					(</div>)					# $4 = closing tag
//					\z
//					}xs', $block, $matches))
//				{
//					list(, $div_open, , $div_content, $div_close) = $matches;
//
//					# We can't call Markdown(), because that resets the hash;
//					# that initialization code should be pulled into its own sub, though.
//					$div_content = $this->hashHTMLBlocks($div_content);
//					
//					# Run document gamut methods on the content.
//					foreach ($this->document_gamut as $method => $priority) {
//						$div_content = $this->$method($div_content);
//					}
//
//					$div_open = preg_replace(
//						'{\smarkdown\s*=\s*([\'"]).+?\1}', '', $div_open);
//
//					$graf = $div_open . "\n" . $div_content . "\n" . $div_close;
//				}
				$grafs[$key] = $graf;
			}
		}

		return implode("\n\n", $grafs);
	}


	protected function encodeAttribute($text) {
	#
	# Encode text for a double-quoted HTML attribute. This function
	# is *not* suitable for attributes enclosed in single quotes.
	#
		$text = $this->encodeAmpsAndAngles($text);
		$text = str_replace('"', '&quot;', $text);
		return $text;
	}
	
	
	protected function encodeAmpsAndAngles($text) {
	#
	# Smart processing for ampersands and angle brackets that need to 
	# be encoded. Valid character entities are left alone unless the
	# no-entities mode is set.
	#
		if ($this->no_entities) {
			$text = str_replace('&', '&amp;', $text);
		} else {
			# Ampersand-encoding based entirely on Nat Irons's Amputator
			# MT plugin: <http://bumppo.net/projects/amputator/>
			$text = preg_replace('/&(?!#?[xX]?(?:[0-9a-fA-F]+|\w+);)/', 
								'&amp;', $text);
		}
		# Encode remaining <'s
		$text = str_replace('<', '&lt;', $text);

		return $text;
	}


	protected function doAutoLinks($text) {
		$text = preg_replace_callback('{<((https?|ftp|dict):[^\'">\s]+)>}i', 
			array(&$this, '_doAutoLinks_url_callback'), $text);

		# Email addresses: <address@domain.foo>
		$text = preg_replace_callback('{
			<
			(?:mailto:)?
			(
				(?:
					[-!#$%&\'*+/=?^_`.{|}~\w\x80-\xFF]+
				|
					".*?"
				)
				\@
				(?:
					[-a-z0-9\x80-\xFF]+(\.[-a-z0-9\x80-\xFF]+)*\.[a-z]+
				|
					\[[\d.a-fA-F:]+\]	# IPv4 & IPv6
				)
			)
			>
			}xi',
			array(&$this, '_doAutoLinks_email_callback'), $text);
		$text = preg_replace_callback('{<(tel:([^\'">\s]+))>}i',array(&$this, '_doAutoLinks_tel_callback'), $text);

		return $text;
	}
	protected function _doAutoLinks_tel_callback($matches) {
		$url = $this->encodeAttribute($matches[1]);
		$tel = $this->encodeAttribute($matches[2]);
		$link = "<a href=\"$url\">$tel</a>";
		return $this->hashPart($link);
	}
	protected function _doAutoLinks_url_callback($matches) {
		$url = $this->encodeAttribute($matches[1]);
		$link = "<a href=\"$url\">$url</a>";
		return $this->hashPart($link);
	}
	protected function _doAutoLinks_email_callback($matches) {
		$address = $matches[1];
		$link = $this->encodeEmailAddress($address);
		return $this->hashPart($link);
	}


	protected function encodeEmailAddress($addr) {
	#
	#	Input: an email address, e.g. "foo@example.com"
	#
	#	Output: the email address as a mailto link, with each character
	#		of the address encoded as either a decimal or hex entity, in
	#		the hopes of foiling most address harvesting spam bots. E.g.:
	#
	#	  <p><a href="&#109;&#x61;&#105;&#x6c;&#116;&#x6f;&#58;&#x66;o&#111;
	#        &#x40;&#101;&#x78;&#97;&#x6d;&#112;&#x6c;&#101;&#46;&#x63;&#111;
	#        &#x6d;">&#x66;o&#111;&#x40;&#101;&#x78;&#97;&#x6d;&#112;&#x6c;
	#        &#101;&#46;&#x63;&#111;&#x6d;</a></p>
	#
	#	Based by a filter by Matthew Wickline, posted to BBEdit-Talk.
	#   With some optimizations by Milian Wolff.
	#
		$addr = "mailto:" . $addr;
		$chars = preg_split('/(?<!^)(?!$)/', $addr);
		$seed = (int)abs(crc32($addr) / strlen($addr)); # Deterministic seed.
		
		foreach ($chars as $key => $char) {
			$ord = ord($char);
			# Ignore non-ascii chars.
			if ($ord < 128) {
				$r = ($seed * (1 + $key)) % 100; # Pseudo-random function.
				# roughly 10% raw, 45% hex, 45% dec
				# '@' *must* be encoded. I insist.
				if ($r > 90 && $char != '@') /* do nothing */;
				else if ($r < 45) $chars[$key] = '&#x'.dechex($ord).';';
				else              $chars[$key] = '&#'.$ord.';';
			}
		}
		
		$addr = implode('', $chars);
		$text = implode('', array_slice($chars, 7)); # text without `mailto:`
		$addr = "<a href=\"$addr\">$text</a>";

		return $addr;
	}


	protected function parseSpan($str) {
	#
	# Take the string $str and parse it into tokens, hashing embeded HTML,
	# escaped characters and handling code spans.
	#
		$output = '';
		
		$span_re = '{
				(
					\\\\'.$this->escape_chars_re.'
				|
					(?<![`\\\\])
					`+						# code span marker
			'.( $this->no_markup ? '' : '
				|
					<!--    .*?     -->		# comment
				|
					<\?.*?\?> | <%.*?%>		# processing instruction
				|
					<[!$]?[-a-zA-Z0-9:_]+	# regular tags
					(?>
						\s
						(?>[^"\'>]+|"[^"]*"|\'[^\']*\')*
					)?
					>
				|
					<[-a-zA-Z0-9:_]+\s*/> # xml-style empty tag
				|
					</[-a-zA-Z0-9:_]+\s*> # closing tag
			').'
				)
				}xs';

		while (1) {
			#
			# Each loop iteration seach for either the next tag, the next 
			# openning code span marker, or the next escaped character. 
			# Each token is then passed to handleSpanToken.
			#
			$parts = preg_split($span_re, $str, 2, PREG_SPLIT_DELIM_CAPTURE);
			
			# Create token from text preceding tag.
			if ($parts[0] != "") {
				$output .= $parts[0];
			}
			
			# Check if we reach the end.
			if (isset($parts[1])) {
				$output .= $this->handleSpanToken($parts[1], $parts[2]);
				$str = $parts[2];
			}
			else {
				break;
			}
		}
		
		return $output;
	}
	
	
	protected function handleSpanToken($token, &$str) {
	#
	# Handle $token provided by parseSpan by determining its nature and 
	# returning the corresponding value that should replace it.
	#
		switch ($token{0}) {
			case "\\":
				return $this->hashPart("&#". ord($token{1}). ";");
			case "`":
				# Search for end marker in remaining text.
				if (preg_match('/^(.*?[^`])'.preg_quote($token).'(?!`)(.*)$/sm', 
					$str, $matches))
				{
					$str = $matches[2];
					$codespan = $this->makeCodeSpan($matches[1]);
					return $this->hashPart($codespan);
				}
				return $token; // return as text since no ending marker found.
			default:
				return $this->hashPart($token);
		}
	}


	protected function outdent($text) {
	#
	# Remove one level of line-leading tabs or spaces
	#
		return preg_replace('/^(\t|[ ]{1,'.$this->tab_width.'})/m', '', $text);
	}


	# String length function for detab. `_initDetab` will create a function to 
	# hanlde UTF-8 if the default function does not exist.
	protected $utf8_strlen = 'mb_strlen';
	
	protected function detab($text) {
	#
	# Replace tabs with the appropriate amount of space.
	#
		# For each line we separate the line in blocks delemited by
		# tab characters. Then we reconstruct every line by adding the 
		# appropriate number of space between each blocks.
		
		$text = preg_replace_callback('/^.*\t.*$/m',
			array(&$this, '_detab_callback'), $text);

		return $text;
	}
	protected function _detab_callback($matches) {
		$line = $matches[0];
		$strlen = $this->utf8_strlen; # strlen function for UTF-8.
		
		# Split in blocks.
		$blocks = explode("\t", $line);
		# Add each blocks to the line.
		$line = $blocks[0];
		unset($blocks[0]); # Do not add first block twice.
		foreach ($blocks as $block) {
			# Calculate amount of space, insert spaces, insert block.
			$amount = $this->tab_width - 
				$strlen($line, 'UTF-8') % $this->tab_width;
			$line .= str_repeat(" ", $amount) . $block;
		}
		return $line;
	}
	protected function _initDetab() {
	#
	# Check for the availability of the function in the `utf8_strlen` property
	# (initially `mb_strlen`). If the function is not available, create a 
	# function that will loosely count the number of UTF-8 characters with a
	# regular expression.
	#
		if (function_exists($this->utf8_strlen)) return;
		$this->utf8_strlen = create_function('$text', 'return preg_match_all(
			"/[\\\\x00-\\\\xBF]|[\\\\xC0-\\\\xFF][\\\\x80-\\\\xBF]*/", 
			$text, $m);');
	}


	protected function unhash($text) {
	#
	# Swap back in all the tags hashed by _HashHTMLBlocks.
	#
		return preg_replace_callback('/(.)\x1A[0-9]+\1/', 
			array(&$this, '_unhash_callback'), $text);
	}
	protected function _unhash_callback($matches) {
		return $this->html_hashes[$matches[0]];
	}

}


#
# Temporary Markdown Extra Parser Implementation Class
#
# NOTE: DON'T USE THIS CLASS
# Currently the implementation of of Extra resides here in this temporary class.
# This makes it easier to propagate the changes between the three different
# packaging styles of PHP Markdown. When this issue is resolved, this
# MarkdownExtra_TmpImpl class here will disappear and \Michelf\MarkdownExtra
# will contain the code. So please use \Michelf\MarkdownExtra and ignore this
# one.
#

abstract class _MarkdownExtra_TmpImpl extends \Michelf\Markdown {

	### Configuration Variables ###

	# Prefix for footnote ids.
	public $fn_id_prefix = "";
	
	# Optional title attribute for footnote links and backlinks.
	public $fn_link_title = "";
	public $fn_backlink_title = "";
	
	# Optional class attribute for footnote links and backlinks.
	public $fn_link_class = "footnote-ref";
	public $fn_backlink_class = "footnote-backref";

	# Class name for table cell alignment (%% replaced left/center/right)
	# For instance: 'go-%%' becomes 'go-left' or 'go-right' or 'go-center'
	# If empty, the align attribute is used instead of a class name.
	public $table_align_class_tmpl = '';

	# Optional class prefix for fenced code block.
	public $code_class_prefix = "";
	# Class attribute for code blocks goes on the `code` tag;
	# setting this to true will put attributes on the `pre` tag instead.
	public $code_attr_on_pre = false;
	
	# Predefined abbreviations.
	public $predef_abbr = array();


	### Parser Implementation ###

	public function __construct() {
	#
	# Constructor function. Initialize the parser object.
	#
		# Add extra escapable characters before parent constructor 
		# initialize the table.
		$this->escape_chars .= ':|';
		
		# Insert extra document, block, and span transformations. 
		# Parent constructor will do the sorting.
		$this->document_gamut += array(
			"doFencedCodeBlocks" => 5,
			"stripFootnotes"     => 15,
			"stripAbbreviations" => 25,
			"appendFootnotes"    => 50,
			);
		$this->block_gamut += array(
			"doFencedCodeBlocks" => 5,
			"doTables"           => 15,
			"doDefLists"         => 45,
			);
		$this->span_gamut += array(
			"doFootnotes"        => 5,
			"doAbbreviations"    => 70,
			);
		
		parent::__construct();
	}
	
	
	# Extra variables used during extra transformations.
	protected $footnotes = array();
	protected $footnotes_ordered = array();
	protected $footnotes_ref_count = array();
	protected $footnotes_numbers = array();
	protected $abbr_desciptions = array();
	protected $abbr_word_re = '';
	
	# Give the current footnote number.
	protected $footnote_counter = 1;
	
	
	protected function setup() {
	#
	# Setting up Extra-specific variables.
	#
		parent::setup();
		
		$this->footnotes = array();
		$this->footnotes_ordered = array();
		$this->footnotes_ref_count = array();
		$this->footnotes_numbers = array();
		$this->abbr_desciptions = array();
		$this->abbr_word_re = '';
		$this->footnote_counter = 1;
		
		foreach ($this->predef_abbr as $abbr_word => $abbr_desc) {
			if ($this->abbr_word_re)
				$this->abbr_word_re .= '|';
			$this->abbr_word_re .= preg_quote($abbr_word);
			$this->abbr_desciptions[$abbr_word] = trim($abbr_desc);
		}
	}
	
	protected function teardown() {
	#
	# Clearing Extra-specific variables.
	#
		$this->footnotes = array();
		$this->footnotes_ordered = array();
		$this->footnotes_ref_count = array();
		$this->footnotes_numbers = array();
		$this->abbr_desciptions = array();
		$this->abbr_word_re = '';
		
		parent::teardown();
	}
	
	
	### Extra Attribute Parser ###

	# Expression to use to catch attributes (includes the braces)
	protected $id_class_attr_catch_re = '\{((?:[ ]*[#.][-_:a-zA-Z0-9]+){1,})[ ]*\}';
	# Expression to use when parsing in a context when no capture is desired
	protected $id_class_attr_nocatch_re = '\{(?:[ ]*[#.][-_:a-zA-Z0-9]+){1,}[ ]*\}';

	protected function doExtraAttributes($tag_name, $attr) {
	#
	# Parse attributes caught by the $this->id_class_attr_catch_re expression
	# and return the HTML-formatted list of attributes.
	#
	# Currently supported attributes are .class and #id.
	#
		if (empty($attr)) return "";
		
		# Split on components
		preg_match_all('/[#.][-_:a-zA-Z0-9]+/', $attr, $matches);
		$elements = $matches[0];

		# handle classes and ids (only first id taken into account)
		$classes = array();
		$id = false;
		foreach ($elements as $element) {
			if ($element{0} == '.') {
				$classes[] = substr($element, 1);
			} else if ($element{0} == '#') {
				if ($id === false) $id = substr($element, 1);
			}
		}

		# compose attributes as string
		$attr_str = "";
		if (!empty($id)) {
			$attr_str .= ' id="'.$id.'"';
		}
		if (!empty($classes)) {
			$attr_str .= ' class="'.implode(" ", $classes).'"';
		}
		return $attr_str;
	}


	protected function stripLinkDefinitions($text) {
	#
	# Strips link definitions from text, stores the URLs and titles in
	# hash references.
	#
		$less_than_tab = $this->tab_width - 1;

		# Link defs are in the form: ^[id]: url "optional title"
		$text = preg_replace_callback('{
							^[ ]{0,'.$less_than_tab.'}\[(.+)\][ ]?:	# id = $1
							  [ ]*
							  \n?				# maybe *one* newline
							  [ ]*
							(?:
							  <(.+?)>			# url = $2
							|
							  (\S+?)			# url = $3
							)
							  [ ]*
							  \n?				# maybe one newline
							  [ ]*
							(?:
								(?<=\s)			# lookbehind for whitespace
								["(]
								(.*?)			# title = $4
								[")]
								[ ]*
							)?	# title is optional
					(?:[ ]* '.$this->id_class_attr_catch_re.' )?  # $5 = extra id & class attr
							(?:\n+|\Z)
			}xm',
			array(&$this, '_stripLinkDefinitions_callback'),
			$text);
		return $text;
	}
	protected function _stripLinkDefinitions_callback($matches) {
		$link_id = strtolower($matches[1]);
		$url = $matches[2] == '' ? $matches[3] : $matches[2];
		$this->urls[$link_id] = $url;
		$this->titles[$link_id] =& $matches[4];
		$this->ref_attr[$link_id] = $this->doExtraAttributes("", $dummy =& $matches[5]);
		return ''; # String that will replace the block
	}


	### HTML Block Parser ###
	
	# Tags that are always treated as block tags:
	protected $block_tags_re = 'p|div|h[1-6]|blockquote|pre|table|dl|ol|ul|address|form|fieldset|iframe|hr|legend|article|section|nav|aside|hgroup|header|footer|figcaption';
						   
	# Tags treated as block tags only if the opening tag is alone on its line:
	protected $context_block_tags_re = 'script|noscript|ins|del|iframe|object|source|track|param|math|svg|canvas|audio|video';
	
	# Tags where markdown="1" default to span mode:
	protected $contain_span_tags_re = 'p|h[1-6]|li|dd|dt|td|th|legend|address';
	
	# Tags which must not have their contents modified, no matter where 
	# they appear:
	protected $clean_tags_re = 'script|math|svg';
	
	# Tags that do not need to be closed.
	protected $auto_close_tags_re = 'hr|img|param|source|track';
	

	protected function hashHTMLBlocks($text) {
	#
	# Hashify HTML Blocks and "clean tags".
	#
	# We only want to do this for block-level HTML tags, such as headers,
	# lists, and tables. That's because we still want to wrap <p>s around
	# "paragraphs" that are wrapped in non-block-level tags, such as anchors,
	# phrase emphasis, and spans. The list of tags we're looking for is
	# hard-coded.
	#
	# This works by calling _HashHTMLBlocks_InMarkdown, which then calls
	# _HashHTMLBlocks_InHTML when it encounter block tags. When the markdown="1" 
	# attribute is found within a tag, _HashHTMLBlocks_InHTML calls back
	#  _HashHTMLBlocks_InMarkdown to handle the Markdown syntax within the tag.
	# These two functions are calling each other. It's recursive!
	#
		if ($this->no_markup)  return $text;

		#
		# Call the HTML-in-Markdown hasher.
		#
		list($text, ) = $this->_hashHTMLBlocks_inMarkdown($text);
		
		return $text;
	}
	protected function _hashHTMLBlocks_inMarkdown($text, $indent = 0,
										$enclosing_tag_re = '', $span = false)
	{
	#
	# Parse markdown text, calling _HashHTMLBlocks_InHTML for block tags.
	#
	# *   $indent is the number of space to be ignored when checking for code 
	#     blocks. This is important because if we don't take the indent into 
	#     account, something like this (which looks right) won't work as expected:
	#
	#     <div>
	#         <div markdown="1">
	#         Hello World.  <-- Is this a Markdown code block or text?
	#         </div>  <-- Is this a Markdown code block or a real tag?
	#     <div>
	#
	#     If you don't like this, just don't indent the tag on which
	#     you apply the markdown="1" attribute.
	#
	# *   If $enclosing_tag_re is not empty, stops at the first unmatched closing 
	#     tag with that name. Nested tags supported.
	#
	# *   If $span is true, text inside must treated as span. So any double 
	#     newline will be replaced by a single newline so that it does not create 
	#     paragraphs.
	#
	# Returns an array of that form: ( processed text , remaining text )
	#
		if ($text === '') return array('', '');

		# Regex to check for the presense of newlines around a block tag.
		$newline_before_re = '/(?:^\n?|\n\n)*$/';
		$newline_after_re = 
			'{
				^						# Start of text following the tag.
				(?>[ ]*<!--.*?-->)?		# Optional comment.
				[ ]*\n					# Must be followed by newline.
			}xs';
		
		# Regex to match any tag.
		$block_tag_re =
			'{
				(					# $2: Capture whole tag.
					</?					# Any opening or closing tag.
						(?>				# Tag name.
							'.$this->block_tags_re.'			|
							'.$this->context_block_tags_re.'	|
							'.$this->clean_tags_re.'        	|
							(?!\s)'.$enclosing_tag_re.'
						)
						(?:
							(?=[\s"\'/a-zA-Z0-9])	# Allowed characters after tag name.
							(?>
								".*?"		|	# Double quotes (can contain `>`)
								\'.*?\'   	|	# Single quotes (can contain `>`)
								.+?				# Anything but quotes and `>`.
							)*?
						)?
					>					# End of tag.
				|
					<!--    .*?     -->	# HTML Comment
				|
					<\?.*?\?> | <%.*?%>	# Processing instruction
				|
					<!\[CDATA\[.*?\]\]>	# CData Block
				'. ( !$span ? ' # If not in span.
				|
					# Indented code block
					(?: ^[ ]*\n | ^ | \n[ ]*\n )
					[ ]{'.($indent+4).'}[^\n]* \n
					(?>
						(?: [ ]{'.($indent+4).'}[^\n]* | [ ]* ) \n
					)*
				|
					# Fenced code block marker
					(?<= ^ | \n )
					[ ]{0,'.($indent+3).'}(?:~{3,}|`{3,})
									[ ]*
					(?:
					\.?[-_:a-zA-Z0-9]+ # standalone class name
					|
						'.$this->id_class_attr_nocatch_re.' # extra attributes
					)?
					[ ]*
					(?= \n )
				' : '' ). ' # End (if not is span).
				|
					# Code span marker
					# Note, this regex needs to go after backtick fenced
					# code blocks but it should also be kept outside of the
					# "if not in span" condition adding backticks to the parser
					`+
				)
			}xs';

		
		$depth = 0;		# Current depth inside the tag tree.
		$parsed = "";	# Parsed text that will be returned.

		#
		# Loop through every tag until we find the closing tag of the parent
		# or loop until reaching the end of text if no parent tag specified.
		#
		do {
			#
			# Split the text using the first $tag_match pattern found.
			# Text before  pattern will be first in the array, text after
			# pattern will be at the end, and between will be any catches made 
			# by the pattern.
			#
			$parts = preg_split($block_tag_re, $text, 2, 
								PREG_SPLIT_DELIM_CAPTURE);
			
			# If in Markdown span mode, add a empty-string span-level hash 
			# after each newline to prevent triggering any block element.
			if ($span) {
				$void = $this->hashPart("", ':');
				$newline = "$void\n";
				$parts[0] = $void . str_replace("\n", $newline, $parts[0]) . $void;
			}
			
			$parsed .= $parts[0]; # Text before current tag.
			
			# If end of $text has been reached. Stop loop.
			if (count($parts) < 3) {
				$text = "";
				break;
			}
			
			$tag  = $parts[1]; # Tag to handle.
			$text = $parts[2]; # Remaining text after current tag.
			$tag_re = preg_quote($tag); # For use in a regular expression.
			
			#
			# Check for: Fenced code block marker.
			# Note: need to recheck the whole tag to disambiguate backtick
			# fences from code spans
			#
			if (preg_match('{^\n?([ ]{0,'.($indent+3).'})(~{3,}|`{3,})[ ]*(?:\.?[-_:a-zA-Z0-9]+|'.$this->id_class_attr_nocatch_re.')?[ ]*\n?$}', $tag, $capture)) {
				# Fenced code block marker: find matching end marker.
				$fence_indent = strlen($capture[1]); # use captured indent in re
				$fence_re = $capture[2]; # use captured fence in re
				if (preg_match('{^(?>.*\n)*?[ ]{'.($fence_indent).'}'.$fence_re.'[ ]*(?:\n|$)}', $text,
					$matches)) 
				{
					# End marker found: pass text unchanged until marker.
					$parsed .= $tag . $matches[0];
					$text = substr($text, strlen($matches[0]));
				}
				else {
					# No end marker: just skip it.
					$parsed .= $tag;
				}
			}
			#
			# Check for: Indented code block.
			#
			else if ($tag{0} == "\n" || $tag{0} == " ") {
				# Indented code block: pass it unchanged, will be handled 
				# later.
				$parsed .= $tag;
			}
			#
			# Check for: Code span marker
			# Note: need to check this after backtick fenced code blocks
			#
			else if ($tag{0} == "`") {
				# Find corresponding end marker.
				$tag_re = preg_quote($tag);
				if (preg_match('{^(?>.+?|\n(?!\n))*?(?<!`)'.$tag_re.'(?!`)}',
					$text, $matches))
				{
					# End marker found: pass text unchanged until marker.
					$parsed .= $tag . $matches[0];
					$text = substr($text, strlen($matches[0]));
				}
				else {
					# Unmatched marker: just skip it.
					$parsed .= $tag;
				}
			}
			#
			# Check for: Opening Block level tag or
			#            Opening Context Block tag (like ins and del) 
			#               used as a block tag (tag is alone on it's line).
			#
			else if (preg_match('{^<(?:'.$this->block_tags_re.')\b}', $tag) ||
				(	preg_match('{^<(?:'.$this->context_block_tags_re.')\b}', $tag) &&
					preg_match($newline_before_re, $parsed) &&
					preg_match($newline_after_re, $text)	)
				)
			{
				# Need to parse tag and following text using the HTML parser.
				list($block_text, $text) = 
					$this->_hashHTMLBlocks_inHTML($tag . $text, "hashBlock", true);
				
				# Make sure it stays outside of any paragraph by adding newlines.
				$parsed .= "\n\n$block_text\n\n";
			}
			#
			# Check for: Clean tag (like script, math)
			#            HTML Comments, processing instructions.
			#
			else if (preg_match('{^<(?:'.$this->clean_tags_re.')\b}', $tag) ||
				$tag{1} == '!' || $tag{1} == '?')
			{
				# Need to parse tag and following text using the HTML parser.
				# (don't check for markdown attribute)
				list($block_text, $text) = 
					$this->_hashHTMLBlocks_inHTML($tag . $text, "hashClean", false);
				
				$parsed .= $block_text;
			}
			#
			# Check for: Tag with same name as enclosing tag.
			#
			else if ($enclosing_tag_re !== '' &&
				# Same name as enclosing tag.
				preg_match('{^</?(?:'.$enclosing_tag_re.')\b}', $tag))
			{
				#
				# Increase/decrease nested tag count.
				#
				if ($tag{1} == '/')						$depth--;
				else if ($tag{strlen($tag)-2} != '/')	$depth++;

				if ($depth < 0) {
					#
					# Going out of parent element. Clean up and break so we
					# return to the calling function.
					#
					$text = $tag . $text;
					break;
				}
				
				$parsed .= $tag;
			}
			else {
				$parsed .= $tag;
			}
		} while ($depth >= 0);
		
		return array($parsed, $text);
	}
	protected function _hashHTMLBlocks_inHTML($text, $hash_method, $md_attr) {
	#
	# Parse HTML, calling _HashHTMLBlocks_InMarkdown for block tags.
	#
	# *   Calls $hash_method to convert any blocks.
	# *   Stops when the first opening tag closes.
	# *   $md_attr indicate if the use of the `markdown="1"` attribute is allowed.
	#     (it is not inside clean tags)
	#
	# Returns an array of that form: ( processed text , remaining text )
	#
		if ($text === '') return array('', '');
		
		# Regex to match `markdown` attribute inside of a tag.
		$markdown_attr_re = '
			{
				\s*			# Eat whitespace before the `markdown` attribute
				markdown
				\s*=\s*
				(?>
					(["\'])		# $1: quote delimiter		
					(.*?)		# $2: attribute value
					\1			# matching delimiter	
				|
					([^\s>]*)	# $3: unquoted attribute value
				)
				()				# $4: make $3 always defined (avoid warnings)
			}xs';
		
		# Regex to match any tag.
		$tag_re = '{
				(					# $2: Capture whole tag.
					</?					# Any opening or closing tag.
						[\w:$]+			# Tag name.
						(?:
							(?=[\s"\'/a-zA-Z0-9])	# Allowed characters after tag name.
							(?>
								".*?"		|	# Double quotes (can contain `>`)
								\'.*?\'   	|	# Single quotes (can contain `>`)
								.+?				# Anything but quotes and `>`.
							)*?
						)?
					>					# End of tag.
				|
					<!--    .*?     -->	# HTML Comment
				|
					<\?.*?\?> | <%.*?%>	# Processing instruction
				|
					<!\[CDATA\[.*?\]\]>	# CData Block
				)
			}xs';
		
		$original_text = $text;		# Save original text in case of faliure.
		
		$depth		= 0;	# Current depth inside the tag tree.
		$block_text	= "";	# Temporary text holder for current text.
		$parsed		= "";	# Parsed text that will be returned.

		#
		# Get the name of the starting tag.
		# (This pattern makes $base_tag_name_re safe without quoting.)
		#
		if (preg_match('/^<([\w:$]*)\b/', $text, $matches))
			$base_tag_name_re = $matches[1];

		#
		# Loop through every tag until we find the corresponding closing tag.
		#
		do {
			#
			# Split the text using the first $tag_match pattern found.
			# Text before  pattern will be first in the array, text after
			# pattern will be at the end, and between will be any catches made 
			# by the pattern.
			#
			$parts = preg_split($tag_re, $text, 2, PREG_SPLIT_DELIM_CAPTURE);
			
			if (count($parts) < 3) {
				#
				# End of $text reached with unbalenced tag(s).
				# In that case, we return original text unchanged and pass the
				# first character as filtered to prevent an infinite loop in the 
				# parent function.
				#
				return array($original_text{0}, substr($original_text, 1));
			}
			
			$block_text .= $parts[0]; # Text before current tag.
			$tag         = $parts[1]; # Tag to handle.
			$text        = $parts[2]; # Remaining text after current tag.
			
			#
			# Check for: Auto-close tag (like <hr/>)
			#			 Comments and Processing Instructions.
			#
			if (preg_match('{^</?(?:'.$this->auto_close_tags_re.')\b}', $tag) ||
				$tag{1} == '!' || $tag{1} == '?')
			{
				# Just add the tag to the block as if it was text.
				$block_text .= $tag;
			}
			else {
				#
				# Increase/decrease nested tag count. Only do so if
				# the tag's name match base tag's.
				#
				if (preg_match('{^</?'.$base_tag_name_re.'\b}', $tag)) {
					if ($tag{1} == '/')						$depth--;
					else if ($tag{strlen($tag)-2} != '/')	$depth++;
				}
				
				#
				# Check for `markdown="1"` attribute and handle it.
				#
				if ($md_attr && 
					preg_match($markdown_attr_re, $tag, $attr_m) &&
					preg_match('/^1|block|span$/', $attr_m[2] . $attr_m[3]))
				{
					# Remove `markdown` attribute from opening tag.
					$tag = preg_replace($markdown_attr_re, '', $tag);
					
					# Check if text inside this tag must be parsed in span mode.
					$this->mode = $attr_m[2] . $attr_m[3];
					$span_mode = $this->mode == 'span' || $this->mode != 'block' &&
						preg_match('{^<(?:'.$this->contain_span_tags_re.')\b}', $tag);
					
					# Calculate indent before tag.
					if (preg_match('/(?:^|\n)( *?)(?! ).*?$/', $block_text, $matches)) {
						$strlen = $this->utf8_strlen;
						$indent = $strlen($matches[1], 'UTF-8');
					} else {
						$indent = 0;
					}
					
					# End preceding block with this tag.
					$block_text .= $tag;
					$parsed .= $this->$hash_method($block_text);
					
					# Get enclosing tag name for the ParseMarkdown function.
					# (This pattern makes $tag_name_re safe without quoting.)
					preg_match('/^<([\w:$]*)\b/', $tag, $matches);
					$tag_name_re = $matches[1];
					
					# Parse the content using the HTML-in-Markdown parser.
					list ($block_text, $text)
						= $this->_hashHTMLBlocks_inMarkdown($text, $indent, 
							$tag_name_re, $span_mode);
					
					# Outdent markdown text.
					if ($indent > 0) {
						$block_text = preg_replace("/^[ ]{1,$indent}/m", "", 
													$block_text);
					}
					
					# Append tag content to parsed text.
					if (!$span_mode)	$parsed .= "\n\n$block_text\n\n";
					else				$parsed .= "$block_text";
					
					# Start over with a new block.
					$block_text = "";
				}
				else $block_text .= $tag;
			}
			
		} while ($depth > 0);
		
		#
		# Hash last block text that wasn't processed inside the loop.
		#
		$parsed .= $this->$hash_method($block_text);
		
		return array($parsed, $text);
	}


	protected function hashClean($text) {
	#
	# Called whenever a tag must be hashed when a function inserts a "clean" tag
	# in $text, it passes through this function and is automaticaly escaped, 
	# blocking invalid nested overlap.
	#
		return $this->hashPart($text, 'C');
	}


	protected function doAnchors($text) {
	#
	# Turn Markdown link shortcuts into XHTML <a> tags.
	#
		if ($this->in_anchor) return $text;
		$this->in_anchor = true;
		
		#
		# First, handle reference-style links: [link text] [id]
		#
		$text = preg_replace_callback('{
			(					# wrap whole match in $1
			  \[
				('.$this->nested_brackets_re.')	# link text = $2
			  \]

			  [ ]?				# one optional space
			  (?:\n[ ]*)?		# one optional newline followed by spaces

			  \[
				(.*?)		# id = $3
			  \]
			)
			}xs',
			array(&$this, '_doAnchors_reference_callback'), $text);

		#
		# Next, inline-style links: [link text](url "optional title")
		#
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  \[
				('.$this->nested_brackets_re.')	# link text = $2
			  \]
			  \(			# literal paren
				[ \n]*
				(?:
					<(.+?)>	# href = $3
				|
					('.$this->nested_url_parenthesis_re.')	# href = $4
				)
				[ \n]*
				(			# $5
				  ([\'"])	# quote char = $6
				  (.*?)		# Title = $7
				  \6		# matching quote
				  [ \n]*	# ignore any spaces/tabs between closing quote and )
				)?			# title is optional
			  \)
			  (?:[ ]? '.$this->id_class_attr_catch_re.' )?	 # $8 = id/class attributes
			)
			}xs',
			array(&$this, '_doAnchors_inline_callback'), $text);

		#
		# Last, handle reference-style shortcuts: [link text]
		# These must come last in case you've also got [link text][1]
		# or [link text](/foo)
		#
		$text = preg_replace_callback('{
			(					# wrap whole match in $1
			  \[
				([^\[\]]+)		# link text = $2; can\'t contain [ or ]
			  \]
			)
			}xs',
			array(&$this, '_doAnchors_reference_callback'), $text);

		$this->in_anchor = false;
		return $text;
	}
	protected function _doAnchors_reference_callback($matches) {
		$whole_match =  $matches[1];
		$link_text   =  $matches[2];
		$link_id     =& $matches[3];

		if ($link_id == "") {
			# for shortcut links like [this][] or [this].
			$link_id = $link_text;
		}
		
		# lower-case and turn embedded newlines into spaces
		$link_id = strtolower($link_id);
		$link_id = preg_replace('{[ ]?\n}', ' ', $link_id);

		if (isset($this->urls[$link_id])) {
			$url = $this->urls[$link_id];
			$url = $this->encodeAttribute($url);
			
			$result = "<a href=\"$url\"";
			if ( isset( $this->titles[$link_id] ) ) {
				$title = $this->titles[$link_id];
				$title = $this->encodeAttribute($title);
				$result .=  " title=\"$title\"";
			}
			if (isset($this->ref_attr[$link_id]))
				$result .= $this->ref_attr[$link_id];
		
			$link_text = $this->runSpanGamut($link_text);
			$result .= ">$link_text</a>";
			$result = $this->hashPart($result);
		}
		else {
			$result = $whole_match;
		}
		return $result;
	}
	protected function _doAnchors_inline_callback($matches) {
		$whole_match	=  $matches[1];
		$link_text		=  $this->runSpanGamut($matches[2]);
		$url			=  $matches[3] == '' ? $matches[4] : $matches[3];
		$title			=& $matches[7];
		$attr  = $this->doExtraAttributes("a", $dummy =& $matches[8]);


		$url = $this->encodeAttribute($url);

		$result = "<a href=\"$url\"";
		if (isset($title)) {
			$title = $this->encodeAttribute($title);
			$result .=  " title=\"$title\"";
		}
		$result .= $attr;
		
		$link_text = $this->runSpanGamut($link_text);
		$result .= ">$link_text</a>";

		return $this->hashPart($result);
	}


	protected function doImages($text) {
	#
	# Turn Markdown image shortcuts into <img> tags.
	#
		#
		# First, handle reference-style labeled images: ![alt text][id]
		#
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  !\[
				('.$this->nested_brackets_re.')		# alt text = $2
			  \]

			  [ ]?				# one optional space
			  (?:\n[ ]*)?		# one optional newline followed by spaces

			  \[
				(.*?)		# id = $3
			  \]

			)
			}xs', 
			array(&$this, '_doImages_reference_callback'), $text);

		#
		# Next, handle inline images:  ![alt text](url "optional title")
		# Don't forget: encode * and _
		#
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  !\[
				('.$this->nested_brackets_re.')		# alt text = $2
			  \]
			  \s?			# One optional whitespace character
			  \(			# literal paren
				[ \n]*
				(?:
					<(\S*)>	# src url = $3
				|
					('.$this->nested_url_parenthesis_re.')	# src url = $4
				)
				[ \n]*
				(			# $5
				  ([\'"])	# quote char = $6
				  (.*?)		# title = $7
				  \6		# matching quote
				  [ \n]*
				)?			# title is optional
			  \)
			  (?:[ ]? '.$this->id_class_attr_catch_re.' )?	 # $8 = id/class attributes
			)
			}xs',
			array(&$this, '_doImages_inline_callback'), $text);

		return $text;
	}
	protected function _doImages_reference_callback($matches) {
		$whole_match = $matches[1];
		$alt_text    = $matches[2];
		$link_id     = strtolower($matches[3]);

		if ($link_id == "") {
			$link_id = strtolower($alt_text); # for shortcut links like ![this][].
		}

		$alt_text = $this->encodeAttribute($alt_text);
		if (isset($this->urls[$link_id])) {
			$url = $this->encodeAttribute($this->urls[$link_id]);
			$result = "<img src=\"$url\" alt=\"$alt_text\"";
			if (isset($this->titles[$link_id])) {
				$title = $this->titles[$link_id];
				$title = $this->encodeAttribute($title);
				$result .=  " title=\"$title\"";
			}
			if (isset($this->ref_attr[$link_id]))
				$result .= $this->ref_attr[$link_id];
			$result .= $this->empty_element_suffix;
			$result = $this->hashPart($result);
		}
		else {
			# If there's no such link ID, leave intact:
			$result = $whole_match;
		}

		return $result;
	}
	protected function _doImages_inline_callback($matches) {
		$whole_match	= $matches[1];
		$alt_text		= $matches[2];
		$url			= $matches[3] == '' ? $matches[4] : $matches[3];
		$title			=& $matches[7];
		$attr  = $this->doExtraAttributes("img", $dummy =& $matches[8]);

		$alt_text = $this->encodeAttribute($alt_text);
		$url = $this->encodeAttribute($url);
		$result = "<img src=\"$url\" alt=\"$alt_text\"";
		if (isset($title)) {
			$title = $this->encodeAttribute($title);
			$result .=  " title=\"$title\""; # $title already quoted
		}
		$result .= $attr;
		$result .= $this->empty_element_suffix;

		return $this->hashPart($result);
	}


	protected function doHeaders($text) {
	#
	# Redefined to add id and class attribute support.
	#
		# Setext-style headers:
		#	  Header 1  {#header1}
		#	  ========
		#  
		#	  Header 2  {#header2 .class1 .class2}
		#	  --------
		#
		$text = preg_replace_callback(
			'{
				(^.+?)								# $1: Header text
				(?:[ ]+ '.$this->id_class_attr_catch_re.' )?	 # $3 = id/class attributes
				[ ]*\n(=+|-+)[ ]*\n+				# $3: Header footer
			}mx',
			array(&$this, '_doHeaders_callback_setext'), $text);

		# atx-style headers:
		#	# Header 1        {#header1}
		#	## Header 2       {#header2}
		#	## Header 2 with closing hashes ##  {#header3.class1.class2}
		#	...
		#	###### Header 6   {.class2}
		#
		$text = preg_replace_callback('{
				^(\#{1,6})	# $1 = string of #\'s
				[ ]*
				(.+?)		# $2 = Header text
				[ ]*
				\#*			# optional closing #\'s (not counted)
				(?:[ ]+ '.$this->id_class_attr_catch_re.' )?	 # $3 = id/class attributes
				[ ]*
				\n+
			}xm',
			array(&$this, '_doHeaders_callback_atx'), $text);

		return $text;
	}
	protected function _doHeaders_callback_setext($matches) {
		if ($matches[3] == '-' && preg_match('{^- }', $matches[1]))
			return $matches[0];
		$level = $matches[3]{0} == '=' ? 1 : 2;
		$attr  = $this->doExtraAttributes("h$level", $dummy =& $matches[2]);
		$block = "<h$level$attr>".$this->runSpanGamut($matches[1])."</h$level>";
		return "\n" . $this->hashBlock($block) . "\n\n";
	}
	protected function _doHeaders_callback_atx($matches) {
		$level = strlen($matches[1]);
		$attr  = $this->doExtraAttributes("h$level", $dummy =& $matches[3]);
		$block = "<h$level$attr>".$this->runSpanGamut($matches[2])."</h$level>";
		return "\n" . $this->hashBlock($block) . "\n\n";
	}


	protected function doTables($text) {
	#
	# Form HTML tables.
	#
		$less_than_tab = $this->tab_width - 1;
		#
		# Find tables with leading pipe.
		#
		#	| Header 1 | Header 2
		#	| -------- | --------
		#	| Cell 1   | Cell 2
		#	| Cell 3   | Cell 4
		#
		$text = preg_replace_callback('
			{
				^							# Start of a line
				[ ]{0,'.$less_than_tab.'}	# Allowed whitespace.
				[|]							# Optional leading pipe (present)
				(.+) \n						# $1: Header row (at least one pipe)
				
				[ ]{0,'.$less_than_tab.'}	# Allowed whitespace.
				[|] ([ ]*[-:]+[-| :]*) \n	# $2: Header underline
				
				(							# $3: Cells
					(?>
						[ ]*				# Allowed whitespace.
						[|] .* \n			# Row content.
					)*
				)
				(?=\n|\Z)					# Stop at final double newline.
			}xm',
			array(&$this, '_doTable_leadingPipe_callback'), $text);
		
		#
		# Find tables without leading pipe.
		#
		#	Header 1 | Header 2
		#	-------- | --------
		#	Cell 1   | Cell 2
		#	Cell 3   | Cell 4
		#
		$text = preg_replace_callback('
			{
				^							# Start of a line
				[ ]{0,'.$less_than_tab.'}	# Allowed whitespace.
				(\S.*[|].*) \n				# $1: Header row (at least one pipe)
				
				[ ]{0,'.$less_than_tab.'}	# Allowed whitespace.
				([-:]+[ ]*[|][-| :]*) \n	# $2: Header underline
				
				(							# $3: Cells
					(?>
						.* [|] .* \n		# Row content
					)*
				)
				(?=\n|\Z)					# Stop at final double newline.
			}xm',
			array(&$this, '_DoTable_callback'), $text);

		return $text;
	}
	protected function _doTable_leadingPipe_callback($matches) {
		$head		= $matches[1];
		$underline	= $matches[2];
		$content	= $matches[3];
		
		# Remove leading pipe for each row.
		$content	= preg_replace('/^ *[|]/m', '', $content);
		
		return $this->_doTable_callback(array($matches[0], $head, $underline, $content));
	}
	protected function _doTable_makeAlignAttr($alignname)
	{
		if (empty($this->table_align_class_tmpl))
			return " align=\"$alignname\"";

		$classname = str_replace('%%', $alignname, $this->table_align_class_tmpl);
		return " class=\"$classname\"";
	}
	protected function _doTable_callback($matches) {
		$head		= $matches[1];
		$underline	= $matches[2];
		$content	= $matches[3];

		# Remove any tailing pipes for each line.
		$head		= preg_replace('/[|] *$/m', '', $head);
		$underline	= preg_replace('/[|] *$/m', '', $underline);
		$content	= preg_replace('/[|] *$/m', '', $content);
		
		# Reading alignement from header underline.
		$separators	= preg_split('/ *[|] */', $underline);
		foreach ($separators as $n => $s) {
			if (preg_match('/^ *-+: *$/', $s))
				$attr[$n] = $this->_doTable_makeAlignAttr('right');
			else if (preg_match('/^ *:-+: *$/', $s))
				$attr[$n] = $this->_doTable_makeAlignAttr('center');
			else if (preg_match('/^ *:-+ *$/', $s))
				$attr[$n] = $this->_doTable_makeAlignAttr('left');
			else
				$attr[$n] = '';
		}
		
		# Parsing span elements, including code spans, character escapes, 
		# and inline HTML tags, so that pipes inside those gets ignored.
		$head		= $this->parseSpan($head);
		$headers	= preg_split('/ *[|] */', $head);
		$col_count	= count($headers);
		$attr       = array_pad($attr, $col_count, '');
		
		# Write column headers.
		$text = "<table>\n";
		$text .= "<thead>\n";
		$text .= "<tr>\n";
		foreach ($headers as $n => $header)
			$text .= "  <th$attr[$n]>".$this->runSpanGamut(trim($header))."</th>\n";
		$text .= "</tr>\n";
		$text .= "</thead>\n";
		
		# Split content by row.
		$rows = explode("\n", trim($content, "\n"));
		
		$text .= "<tbody>\n";
		foreach ($rows as $row) {
			# Parsing span elements, including code spans, character escapes, 
			# and inline HTML tags, so that pipes inside those gets ignored.
			$row = $this->parseSpan($row);
			
			# Split row by cell.
			$row_cells = preg_split('/ *[|] */', $row, $col_count);
			$row_cells = array_pad($row_cells, $col_count, '');
			
			$text .= "<tr>\n";
			foreach ($row_cells as $n => $cell)
				$text .= "  <td$attr[$n]>".$this->runSpanGamut(trim($cell))."</td>\n";
			$text .= "</tr>\n";
		}
		$text .= "</tbody>\n";
		$text .= "</table>";
		
		return $this->hashBlock($text) . "\n";
	}

	
	protected function doDefLists($text) {
	#
	# Form HTML definition lists.
	#
		$less_than_tab = $this->tab_width - 1;

		# Re-usable pattern to match any entire dl list:
		$whole_list_re = '(?>
			(								# $1 = whole list
			  (								# $2
				[ ]{0,'.$less_than_tab.'}
				((?>.*\S.*\n)+)				# $3 = defined term
				\n?
				[ ]{0,'.$less_than_tab.'}:[ ]+ # colon starting definition
			  )
			  (?s:.+?)
			  (								# $4
				  \z
				|
				  \n{2,}
				  (?=\S)
				  (?!						# Negative lookahead for another term
					[ ]{0,'.$less_than_tab.'}
					(?: \S.*\n )+?			# defined term
					\n?
					[ ]{0,'.$less_than_tab.'}:[ ]+ # colon starting definition
				  )
				  (?!						# Negative lookahead for another definition
					[ ]{0,'.$less_than_tab.'}:[ ]+ # colon starting definition
				  )
			  )
			)
		)'; // mx

		$text = preg_replace_callback('{
				(?>\A\n?|(?<=\n\n))
				'.$whole_list_re.'
			}mx',
			array(&$this, '_doDefLists_callback'), $text);

		return $text;
	}
	protected function _doDefLists_callback($matches) {
		# Re-usable patterns to match list item bullets and number markers:
		$list = $matches[1];
		
		# Turn double returns into triple returns, so that we can make a
		# paragraph for the last item in a list, if necessary:
		$result = trim($this->processDefListItems($list));
		$result = "<dl>\n" . $result . "\n</dl>";
		return $this->hashBlock($result) . "\n\n";
	}


	protected function processDefListItems($list_str) {
	#
	#	Process the contents of a single definition list, splitting it
	#	into individual term and definition list items.
	#
		$less_than_tab = $this->tab_width - 1;
		
		# trim trailing blank lines:
		$list_str = preg_replace("/\n{2,}\\z/", "\n", $list_str);

		# Process definition terms.
		$list_str = preg_replace_callback('{
			(?>\A\n?|\n\n+)					# leading line
			(								# definition terms = $1
				[ ]{0,'.$less_than_tab.'}	# leading whitespace
				(?!\:[ ]|[ ])				# negative lookahead for a definition
											#   mark (colon) or more whitespace.
				(?> \S.* \n)+?				# actual term (not whitespace).	
			)			
			(?=\n?[ ]{0,3}:[ ])				# lookahead for following line feed 
											#   with a definition mark.
			}xm',
			array(&$this, '_processDefListItems_callback_dt'), $list_str);

		# Process actual definitions.
		$list_str = preg_replace_callback('{
			\n(\n+)?						# leading line = $1
			(								# marker space = $2
				[ ]{0,'.$less_than_tab.'}	# whitespace before colon
				\:[ ]+						# definition mark (colon)
			)
			((?s:.+?))						# definition text = $3
			(?= \n+ 						# stop at next definition mark,
				(?:							# next term or end of text
					[ ]{0,'.$less_than_tab.'} \:[ ]	|
					<dt> | \z
				)						
			)					
			}xm',
			array(&$this, '_processDefListItems_callback_dd'), $list_str);

		return $list_str;
	}
	protected function _processDefListItems_callback_dt($matches) {
		$terms = explode("\n", trim($matches[1]));
		$text = '';
		foreach ($terms as $term) {
			$term = $this->runSpanGamut(trim($term));
			$text .= "\n<dt>" . $term . "</dt>";
		}
		return $text . "\n";
	}
	protected function _processDefListItems_callback_dd($matches) {
		$leading_line	= $matches[1];
		$marker_space	= $matches[2];
		$def			= $matches[3];

		if ($leading_line || preg_match('/\n{2,}/', $def)) {
			# Replace marker with the appropriate whitespace indentation
			$def = str_repeat(' ', strlen($marker_space)) . $def;
			$def = $this->runBlockGamut($this->outdent($def . "\n\n"));
			$def = "\n". $def ."\n";
		}
		else {
			$def = rtrim($def);
			$def = $this->runSpanGamut($this->outdent($def));
		}

		return "\n<dd>" . $def . "</dd>\n";
	}


	protected function doFencedCodeBlocks($text) {
	#
	# Adding the fenced code block syntax to regular Markdown:
	#
	# ~~~
	# Code block
	# ~~~
	#
		$less_than_tab = $this->tab_width;
		
		$text = preg_replace_callback('{
				(?:\n|\A)
				# 1: Opening marker
				(
					(?:~{3,}|`{3,}) # 3 or more tildes/backticks.
				)
				[ ]*
				(?:
					\.?([-_:a-zA-Z0-9]+) # 2: standalone class name
				|
					'.$this->id_class_attr_catch_re.' # 3: Extra attributes
				)?
				[ ]* \n # Whitespace and newline following marker.
				
				# 4: Content
				(
					(?>
						(?!\1 [ ]* \n)	# Not a closing marker.
						.*\n+
					)+
				)
				
				# Closing marker.
				\1 [ ]* (?= \n )
			}xm',
			array(&$this, '_doFencedCodeBlocks_callback'), $text);

		return $text;
	}
	protected function _doFencedCodeBlocks_callback($matches) {
		$classname =& $matches[2];
		$attrs     =& $matches[3];
		$codeblock = $matches[4];
		$codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES);
		$codeblock = preg_replace_callback('/^\n+/',
			array(&$this, '_doFencedCodeBlocks_newlines'), $codeblock);

		if ($classname != "") {
			if ($classname{0} == '.')
				$classname = substr($classname, 1);
			$attr_str = ' class="'.$this->code_class_prefix.$classname.'"';
		} else {
			$attr_str = $this->doExtraAttributes($this->code_attr_on_pre ? "pre" : "code", $attrs);
		}
		$pre_attr_str  = $this->code_attr_on_pre ? $attr_str : '';
		$code_attr_str = $this->code_attr_on_pre ? '' : $attr_str;
		$codeblock  = "<pre$pre_attr_str><code$code_attr_str>$codeblock</code></pre>";
		
		return "\n\n".$this->hashBlock($codeblock)."\n\n";
	}
	protected function _doFencedCodeBlocks_newlines($matches) {
		return str_repeat("<br$this->empty_element_suffix", 
			strlen($matches[0]));
	}


	#
	# Redefining emphasis markers so that emphasis by underscore does not
	# work in the middle of a word.
	#
	protected $em_relist = array(
		''  => '(?:(?<!\*)\*(?!\*)|(?<![a-zA-Z0-9_])_(?!_))(?=\S|$)(?![\.,:;]\s)',
		'*' => '(?<=\S|^)(?<!\*)\*(?!\*)',
		'_' => '(?<=\S|^)(?<!_)_(?![a-zA-Z0-9_])',
		);
	protected $strong_relist = array(
		''   => '(?:(?<!\*)\*\*(?!\*)|(?<![a-zA-Z0-9_])__(?!_))(?=\S|$)(?![\.,:;]\s)',
		'**' => '(?<=\S|^)(?<!\*)\*\*(?!\*)',
		'__' => '(?<=\S|^)(?<!_)__(?![a-zA-Z0-9_])',
		);
	protected $em_strong_relist = array(
		''    => '(?:(?<!\*)\*\*\*(?!\*)|(?<![a-zA-Z0-9_])___(?!_))(?=\S|$)(?![\.,:;]\s)',
		'***' => '(?<=\S|^)(?<!\*)\*\*\*(?!\*)',
		'___' => '(?<=\S|^)(?<!_)___(?![a-zA-Z0-9_])',
		);


	protected function formParagraphs($text) {
	#
	#	Params:
	#		$text - string to process with html <p> tags
	#
		# Strip leading and trailing lines:
		$text = preg_replace('/\A\n+|\n+\z/', '', $text);
		
		$grafs = preg_split('/\n{2,}/', $text, -1, PREG_SPLIT_NO_EMPTY);

		#
		# Wrap <p> tags and unhashify HTML blocks
		#
		foreach ($grafs as $key => $value) {
			$value = trim($this->runSpanGamut($value));
			
			# Check if this should be enclosed in a paragraph.
			# Clean tag hashes & block tag hashes are left alone.
			$is_p = !preg_match('/^B\x1A[0-9]+B|^C\x1A[0-9]+C$/', $value);
			
			if ($is_p) {
				$value = "<p>$value</p>";
			}
			$grafs[$key] = $value;
		}
		
		# Join grafs in one text, then unhash HTML tags. 
		$text = implode("\n\n", $grafs);
		
		# Finish by removing any tag hashes still present in $text.
		$text = $this->unhash($text);
		
		return $text;
	}
	
	
	### Footnotes
	
	protected function stripFootnotes($text) {
	#
	# Strips link definitions from text, stores the URLs and titles in
	# hash references.
	#
		$less_than_tab = $this->tab_width - 1;

		# Link defs are in the form: [^id]: url "optional title"
		$text = preg_replace_callback('{
			^[ ]{0,'.$less_than_tab.'}\[\^(.+?)\][ ]?:	# note_id = $1
			  [ ]*
			  \n?					# maybe *one* newline
			(						# text = $2 (no blank lines allowed)
				(?:					
					.+				# actual text
				|
					\n				# newlines but 
					(?!\[\^.+?\]:\s)# negative lookahead for footnote marker.
					(?!\n+[ ]{0,3}\S)# ensure line is not blank and followed 
									# by non-indented content
				)*
			)		
			}xm',
			array(&$this, '_stripFootnotes_callback'),
			$text);
		return $text;
	}
	protected function _stripFootnotes_callback($matches) {
		$note_id = $this->fn_id_prefix . $matches[1];
		$this->footnotes[$note_id] = $this->outdent($matches[2]);
		return ''; # String that will replace the block
	}


	protected function doFootnotes($text) {
	#
	# Replace footnote references in $text [^id] with a special text-token 
	# which will be replaced by the actual footnote marker in appendFootnotes.
	#
		if (!$this->in_anchor) {
			$text = preg_replace('{\[\^(.+?)\]}', "F\x1Afn:\\1\x1A:", $text);
		}
		return $text;
	}

	
	protected function appendFootnotes($text) {
	#
	# Append footnote list to text.
	#
		$text = preg_replace_callback('{F\x1Afn:(.*?)\x1A:}', 
			array(&$this, '_appendFootnotes_callback'), $text);
	
		if (!empty($this->footnotes_ordered)) {
			$text .= "\n\n";
			$text .= "<div class=\"footnotes\">\n";
			$text .= "<hr". $this->empty_element_suffix ."\n";
			$text .= "<ol>\n\n";

			$attr = "";
			if ($this->fn_backlink_class != "") {
				$class = $this->fn_backlink_class;
				$class = $this->encodeAttribute($class);
				$attr .= " class=\"$class\"";
			}
			if ($this->fn_backlink_title != "") {
				$title = $this->fn_backlink_title;
				$title = $this->encodeAttribute($title);
				$attr .= " title=\"$title\"";
			}
			$num = 0;
			
			while (!empty($this->footnotes_ordered)) {
				$footnote = reset($this->footnotes_ordered);
				$note_id = key($this->footnotes_ordered);
				unset($this->footnotes_ordered[$note_id]);
				$ref_count = $this->footnotes_ref_count[$note_id];
				unset($this->footnotes_ref_count[$note_id]);
				unset($this->footnotes[$note_id]);
				
				$footnote .= "\n"; # Need to append newline before parsing.
				$footnote = $this->runBlockGamut("$footnote\n");				
				$footnote = preg_replace_callback('{F\x1Afn:(.*?)\x1A:}', 
					array(&$this, '_appendFootnotes_callback'), $footnote);
				
				$attr = str_replace("%%", ++$num, $attr);
				$note_id = $this->encodeAttribute($note_id);

				# Prepare backlink, multiple backlinks if multiple references
				$backlink = "<a href=\"#fnref:$note_id\"$attr>&#8617;</a>";
				for ($ref_num = 2; $ref_num <= $ref_count; ++$ref_num) {
					$backlink .= " <a href=\"#fnref$ref_num:$note_id\"$attr>&#8617;</a>";
				}
				# Add backlink to last paragraph; create new paragraph if needed.
				if (preg_match('{</p>$}', $footnote)) {
					$footnote = substr($footnote, 0, -4) . "&#160;$backlink</p>";
				} else {
					$footnote .= "\n\n<p>$backlink</p>";
				}
				
				$text .= "<li id=\"fn:$note_id\">\n";
				$text .= $footnote . "\n";
				$text .= "</li>\n\n";
			}
			
			$text .= "</ol>\n";
			$text .= "</div>";
		}
		return $text;
	}
	protected function _appendFootnotes_callback($matches) {
		$node_id = $this->fn_id_prefix . $matches[1];
		
		# Create footnote marker only if it has a corresponding footnote *and*
		# the footnote hasn't been used by another marker.
		if (isset($this->footnotes[$node_id])) {
			$num =& $this->footnotes_numbers[$node_id];
			if (!isset($num)) {
				# Transfer footnote content to the ordered list and give it its
				# number
				$this->footnotes_ordered[$node_id] = $this->footnotes[$node_id];
				$this->footnotes_ref_count[$node_id] = 1;
				$num = $this->footnote_counter++;
				$ref_count_mark = '';
			} else {
				$ref_count_mark = $this->footnotes_ref_count[$node_id] += 1;
			}

			$attr = "";
			if ($this->fn_link_class != "") {
				$class = $this->fn_link_class;
				$class = $this->encodeAttribute($class);
				$attr .= " class=\"$class\"";
			}
			if ($this->fn_link_title != "") {
				$title = $this->fn_link_title;
				$title = $this->encodeAttribute($title);
				$attr .= " title=\"$title\"";
			}
			
			$attr = str_replace("%%", $num, $attr);
			$node_id = $this->encodeAttribute($node_id);
			
			return
				"<sup id=\"fnref$ref_count_mark:$node_id\">".
				"<a href=\"#fn:$node_id\"$attr>$num</a>".
				"</sup>";
		}
		
		return "[^".$matches[1]."]";
	}
		
	
	### Abbreviations ###
	
	protected function stripAbbreviations($text) {
	#
	# Strips abbreviations from text, stores titles in hash references.
	#
		$less_than_tab = $this->tab_width - 1;

		# Link defs are in the form: [id]*: url "optional title"
		$text = preg_replace_callback('{
			^[ ]{0,'.$less_than_tab.'}\*\[(.+?)\][ ]?:	# abbr_id = $1
			(.*)					# text = $2 (no blank lines allowed)	
			}xm',
			array(&$this, '_stripAbbreviations_callback'),
			$text);
		return $text;
	}
	protected function _stripAbbreviations_callback($matches) {
		$abbr_word = $matches[1];
		$abbr_desc = $matches[2];
		if ($this->abbr_word_re)
			$this->abbr_word_re .= '|';
		$this->abbr_word_re .= preg_quote($abbr_word);
		$this->abbr_desciptions[$abbr_word] = trim($abbr_desc);
		return ''; # String that will replace the block
	}
	
	
	protected function doAbbreviations($text) {
	#
	# Find defined abbreviations in text and wrap them in <abbr> elements.
	#
		if ($this->abbr_word_re) {
			// cannot use the /x modifier because abbr_word_re may 
			// contain significant spaces:
			$text = preg_replace_callback('{'.
				'(?<![\w\x1A])'.
				'(?:'.$this->abbr_word_re.')'.
				'(?![\w\x1A])'.
				'}', 
				array(&$this, '_doAbbreviations_callback'), $text);
		}
		return $text;
	}
	protected function _doAbbreviations_callback($matches) {
		$abbr = $matches[0];
		if (isset($this->abbr_desciptions[$abbr])) {
			$desc = $this->abbr_desciptions[$abbr];
			if (empty($desc)) {
				return $this->hashPart("<abbr>$abbr</abbr>");
			} else {
				$desc = $this->encodeAttribute($desc);
				return $this->hashPart("<abbr title=\"$desc\">$abbr</abbr>");
			}
		} else {
			return $matches[0];
		}
	}

}


}




// vendor/michelf/php-markdown/Michelf/MarkdownExtra.php


#
# Markdown Extra  -  A text-to-HTML conversion tool for web writers
#
# PHP Markdown Extra
# Copyright (c) 2004-2013 Michel Fortin  
# <http://michelf.com/projects/php-markdown/>
#
# Original Markdown
# Copyright (c) 2004-2006 John Gruber  
# <http://daringfireball.net/projects/markdown/>
#
namespace Michelf {


# Just force Michelf/Markdown.php to load. This is needed to load
# the temporary implementation class. See below for details.
\Michelf\Markdown::MARKDOWNLIB_VERSION;

#
# Markdown Extra Parser Class
#
# Note: Currently the implementation resides in the temporary class
# \Michelf\MarkdownExtra_TmpImpl (in the same file as \Michelf\Markdown).
# This makes it easier to propagate the changes between the three different
# packaging styles of PHP Markdown. Once this issue is resolved, the
# _MarkdownExtra_TmpImpl will disappear and this one will contain the code.
#

class MarkdownExtra extends \Michelf\_MarkdownExtra_TmpImpl {

	### Parser Implementation ###

	# Temporarily, the implemenation is in the _MarkdownExtra_TmpImpl class.
	# See note above.

}



}






namespace {

// vendor/pimple/pimple/lib/Pimple.php



/*
 * This file is part of Pimple.
 *
 * Copyright (c) 2009 Fabien Potencier
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Pimple main class.
 *
 * @package pimple
 * @author  Fabien Potencier
 */
class Pimple implements ArrayAccess
{
    protected $values = array();

    /**
     * Instantiate the container.
     *
     * Objects and parameters can be passed as argument to the constructor.
     *
     * @param array $config The parameters or objects.
     */
    public function __construct (array $config = array())
    {
        $this->values = $config;
    }

    /**
     * Sets a parameter or an object.
     *
     * Objects must be defined as Closures.
     *
     * Allowing any PHP callable leads to difficult to debug problems
     * as function names (strings) are callable (creating a function with
     * the same a name as an existing parameter would break your container).
     *
     * @param string $id    The unique identifier for the parameter or object
     * @param mixed  $value The value of the parameter or a closure to defined an object
     */
    public function offsetSet($id, $value)
    {
        $this->values[$id] = $value;
    }

    /**
     * Gets a parameter or an object.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or an object
     *
     * @throws InvalidArgumentException if the identifier is not defined
     */
    public function offsetGet($id)
    {
        if (!array_key_exists($id, $this->values)) {
            throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        $isFactory = is_object($this->values[$id]) && method_exists($this->values[$id], '__invoke');

        return $isFactory ? $this->values[$id]($this) : $this->values[$id];
    }

    /**
     * Checks if a parameter or an object is set.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return Boolean
     */
    public function offsetExists($id)
    {
        return array_key_exists($id, $this->values);
    }

    /**
     * Unsets a parameter or an object.
     *
     * @param string $id The unique identifier for the parameter or object
     */
    public function offsetUnset($id)
    {
        unset($this->values[$id]);
    }

    /**
     * Returns a closure that stores the result of the given closure for
     * uniqueness in the scope of this instance of Pimple.
     *
     * @param Closure $callable A closure to wrap for uniqueness
     *
     * @return Closure The wrapped closure
     */
    public static function share(Closure $callable)
    {
        return function ($c) use ($callable) {
            static $object;

            if (null === $object) {
                $object = $callable($c);
            }

            return $object;
        };
    }

    /**
     * Protects a callable from being interpreted as a service.
     *
     * This is useful when you want to store a callable as a parameter.
     *
     * @param Closure $callable A closure to protect from being evaluated
     *
     * @return Closure The protected closure
     */
    public static function protect(Closure $callable)
    {
        return function ($c) use ($callable) {
            return $callable;
        };
    }

    /**
     * Gets a parameter or the closure defining an object.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or the closure defining an object
     *
     * @throws InvalidArgumentException if the identifier is not defined
     */
    public function raw($id)
    {
        if (!array_key_exists($id, $this->values)) {
            throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        return $this->values[$id];
    }

    /**
     * Extends an object definition.
     *
     * Useful when you want to extend an existing object definition,
     * without necessarily loading that object.
     *
     * @param string  $id       The unique identifier for the object
     * @param Closure $callable A closure to extend the original
     *
     * @return Closure The wrapped closure
     *
     * @throws InvalidArgumentException if the identifier is not defined
     */
    public function extend($id, Closure $callable)
    {
        if (!array_key_exists($id, $this->values)) {
            throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        $factory = $this->values[$id];

        if (!($factory instanceof Closure)) {
            throw new InvalidArgumentException(sprintf('Identifier "%s" does not contain an object definition.', $id));
        }

        return $this->values[$id] = function ($c) use ($callable, $factory) {
            return $callable($factory($c), $c);
        };
    }

    /**
     * Returns all defined value names.
     *
     * @return array An array of value names
     */
    public function keys()
    {
        return array_keys($this->values);
    }
}


}




// src/Maki/Markdown.php



namespace Maki {

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

}




// src/Maki/File/Markdown.php



namespace Maki\File {

class Markdown
{
    protected $app;
    protected $filePath;
    protected $directoryPath;
    protected $fileAbsPath;
    protected $name;
    protected $exists = false;
    protected $content;
    protected $loaded = false;
    protected $locked = false;
    protected $breadcrumb = null;

    public function __construct($app, $filePath)
    {
        $this->app = $app;
        $this->filePath = $filePath;
        $this->fileAbsPath = $app['docs.path'].$filePath;
        $this->name = pathinfo($filePath, PATHINFO_BASENAME);

        if (is_file($this->fileAbsPath)) {
            $this->exists = true;
        }

        $cacheDir = $this->app->getCacheDirAbsPath().'docs/';

        if (is_file($cacheDir.$this->name)) {            
            $time = time() - filemtime($cacheDir.$this->name);

            // Last edited more then 2 minutes ago
            if ($time > 120) {
                unlink($cacheDir.$this->name);
            } else {
                // See who editing
                $id = file_get_contents($cacheDir.$this->name);

                // Someone else is editing this file now
                if ($this->app->getSessionId() != $id) {
                    $this->locked = true;
                }
            }
        }
    }

    public function getName()
    {
        return $this->name;
    }

    public function getContent($forceRefresh = false)
    {
        if (($this->exists and ! $this->loaded) or $forceRefresh) {
            $this->content = file_get_contents($this->fileAbsPath);
            $this->loaded = true;
            return $this->content;
        }

        return $this->content;
    }

    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    public function getFilePath()
    {
        return $this->filePath;
    }

    public function getBreadcrumb()
    {
        if ($this->breadcrumb === null) {
            $content = $this->getContent();

            preg_match('/<\!\-\-\-\s*@breadcrumb:(.*)?\-\->/', $content, $match);

            if ( ! isset($match[1])) {
                $this->breadcrumb = array(array(
                    'text'   => $this->getName(),
                    'url'    => $this->getUrl(),
                    'active' => true
                ));
            } else {
                $pages = array();
                $parts = explode(';', trim($match[1]));

                foreach ($parts as $part) {
                    $page = explode('/', $part);
                    end($page);

                    $pages[] = array(
                        'text'      => current($page),
                        'url'       => strpos($part, '.md') === false ? false : $this->app->getUrl().$part,
                        'active'    => false
                    );
                }

                $pages[count($pages)-1]['active'] = true;

                $this->breadcrumb = $pages;
            }
        }

        return $this->breadcrumb;
    }

    public function save()
    {
        if ($this->locked) {
            return false;
        }

        $dirName = pathinfo($this->fileAbsPath, PATHINFO_DIRNAME);

        if ( ! is_dir($dirName)) {
            mkdir($dirName, 0777, true);
        }

        file_put_contents($this->fileAbsPath, $this->content);

        $cacheDir = $this->app->getCacheDirAbsPath().'docs/';

        if ( ! is_dir($cacheDir)) {
            mkdir($cacheDir, 0700, true);
        }

        file_put_contents($cacheDir.$this->name, $this->app->getSessionId());

        return $this;
    }

    public function delete()
    {
        if ($this->locked) {
            return false;
        }

        if ($this->exists) {
            @unlink($this->fileAbsPath);

            $this->exists = false;
            $this->loaded = false;
        }

        return $this;
    }

    public function toHTML()
    {
        return $this->app['parser.markdown']->transform($this->getContent());
    }

    public function getUrl()
    {
        return $this->app->getUrl().$this->filePath;
    }

    public function isNotLocked()
    {
        return $this->locked === false;
    }

    public function isLocked()
    {
        return $this->locked;
    }
}

}




// src/Maki/ThemeManager.php



namespace Maki {

class ThemeManager 
{
    protected $app;
    protected $stylesheets = array();
    protected $activeStylesheet;

    public function __construct(Maki $app)
    {
        $this->app = $app;
    }

    public function getStylesheet($name)
    {
        if ( ! $this->validName($name)) {
            throw new \InvalidArgumentException(sprintf('Stylesheet "%s" has not allowed chars or its name is too long or stylesheet with this name does not exists.', $name));
        }

        if ( ! isset($this->stylesheets[$name])) {
            throw new \InvalidArgumentException(sprintf('Stylesheet "%s" not exist.', $name));
        }

        return file_get_contents($this->app['docroot'].$this->stylesheets[$name]);
    }

    public function addStylesheet($name, $file)
    {
        $this->stylesheets[$name] = $file;

        return $this;
    }

    public function addStylesheets(array $array)
    {
        foreach ($array as $name => $file) {
            $this->addStylesheet($name, $file);
        }

        return $this;
    }

    public function getStylesheets()
    {
        return $this->stylesheets;
    }

    public function getActiveStylesheet()
    {
        return $this->activeStylesheet;
    }

    public function getStylesheetPath($name)
    {
        if ( ! $this->isStylesheetExist($name)) {
            throw new \InvalidArgumentException(sprintf('Stylesheet "%s" not exists.', $name));
        }

        return $this->stylesheets[$name];
    }

    public function setActiveStylesheet($name)
    {
        $this->activeStylesheet = $name;
    }

    public function isStylesheetExist($name)
    {
        return isset($this->stylesheets[$name]);
    }

    public function validName($name)
    {
        if (strlen($name) > 20) {
            return false;
        }

        if ( ! preg_match('/^[_a-z]+$/', $name)) {
            return false;
        }

        return true;
    }

    public function serveResource($name)
    {
        if ( ! preg_match('/^[-a-z0-9_\.\/]+$/', $name) or ! ($resource = $this->app->getResource($name))) {
            $this->app->response('File not found.', 'text/html', 404);
        }

        $ext = pathinfo($name, PATHINFO_EXTENSION);

        switch ($ext) {
            case 'js':
                $type = 'text/javascript';
                break;
            case 'css':
                $type = 'text/css';
                break;
            default:
                $type = 'text/html';
                break;
        }

        $this->app->response($resource, $type);
    }
}

}




// src/Maki/Collection.php



namespace Maki {

class Collection implements \ArrayAccess {
    protected $data = [];

    public function __construct(array $arr = [])
    {
        $this->data = $arr;
    }

    public function get($key, $default = null)
    {
        return $this->offsetExists($key) ? $this->offsetGet($key) : $default;
    }

    public function has($key)
    {
        return $this->offsetExists($key);
    }

    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    public function merge(array $array = [])
    {
        $this->data = array_merge($this->data, $array);
    }

    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);
        $this->offsetUnset($key);

        return $value;
    }

    public function toArray()
    {
        return $this->data;
    }
}



}




// src/Maki/Controller.php



namespace Maki {

abstract class Controller
{
    /**
     * @var Maki
     */
    protected $app;

    public function __construct(Maki $app)
    {
        $this->app = $app;
    }

    public static function match(Maki $app)
    {

    }

    public function isSecured($action)
    {
        return true;
    }

    public function viewResponse($path, array $data = [], $type = 'text/html', $code = 200, $headers = [])
    {
        return $this->app->response($this->app->render($path, $data), $type, $code, $headers);
    }

    public function jsonResponse(array $array, $type = 'text/html', $code = 200, $headers = [])
    {
        return $this->app->response(json_encode($array), $type, $code, $headers);
    }
}

}




// src/Maki/Controller/PageController.php



namespace Maki\Controller {

use Maki\Controller;
use Maki\Maki;

/**
 * Page controller.
 * @package Maki\Controller
 */
class PageController extends Controller
{
    public static function match(Maki $app)
    {
        if ($app['editable'] and isset($_GET['save'])) {
            return 'saveContentAction';
        }

        if ($app['editable'] and isset($_GET['delete'])) {
            return 'deleteAction';
        }

        if (isset($_GET['action']) and $_GET['action'] == 'downloadCode') {
            return 'downloadCodeAction';
        }

        // Default action.
        return 'pageAction';
    }

    /**
     * @param $file
     * @return \Maki\File\Markdown
     */
    protected function createFileInstance($file)
    {
        $app = $this->app;
        $ext = pathinfo($file, PATHINFO_EXTENSION);

        if ( ! isset($app['docs.extensions'][$ext])) {
            throw new \InvalidArgumentException(sprintf('File class for "%s" not exists.', $file));
        }

        $class = '\\Maki\\File\\'.ucfirst($app['docs.extensions'][$ext]);
        return new $class($app, $file);
    }

    public function findSidebarFile($directory)
    {
        $app = $this->app;
        $exts = $app['docs.extensions'];
        $path = $app['docroot'].$app['docs.path'].($directory == '' ? '' : rtrim($directory, '/').'/');
        $sidebarName = $app['docs.navigation_filename'];

        foreach ($exts as $ext => $null) {
            if (is_file($path.$sidebarName.'.'.$ext)) {
                return $sidebarName.'.'.$ext;
            }
        }

        return $sidebarName.'.'.key($exts);
    }

    public function findIndexFile($directory)
    {
        $app = $this->app;

        $exts = $app['docs.extensions'];
        $path = $app['docs.path'].rtrim($directory, '/').'/';
        $indexName = $app['docs.index_filename'];

        foreach ($app['docs.extensions'] as $ext) {
            if (is_file($path.$indexName.'.'.$ext)) {
                return $indexName.'.'.$ext;
            }
        }

        return $indexName.'.'.key($exts);
    }

    protected function createPageFileInstanceFromRequest()
    {
        $url = $this->app->getCurrentUrl();
        $info = pathinfo($url);

        // No file specified, so default index is taken
        if ( ! isset($info['extension'])) {
            $url .= $this->findIndexFile($url);
        }

        return $this->createFileInstance($url);
    }

    public function pageAction()
    {
        $app = $this->app;
        $info = pathinfo($app->getCurrentUrl());
        $dirName = isset($info['dirname']) ? $info['dirname'] : '';

        $nav = $this->createFileInstance($this->findSidebarFile($dirName));
        $page = $this->createPageFileInstanceFromRequest();

        $activeStylesheet = $app->getThemeManager()->getActiveStylesheet();

        $this->viewResponse('resources/views/page.php', [
            'page' => $page,
            'nav' => $nav,
            'editable' => $app['editable'],
            'viewable' => false,
            'editing' => ($app['editable'] and isset($_GET['edit'])),
            'activeStylesheet' => $activeStylesheet,
            'stylesheet' => $app->getThemeManager()->getStylesheetPath($activeStylesheet),
            'editButton' => 'edit'
        ]);
    }

    /**
     * Saves page content.
     */
    public function saveContentAction()
    {
        $page = $this->createPageFileInstanceFromRequest();
        $page->setContent(isset($_POST['content']) ? $_POST['content'] : '')->save();
        $this->jsonResponse(['success' => true]);
    }

    /**
     * Deletes page.
     */
    public function deleteAction()
    {
        $page = $this->createPageFileInstanceFromRequest();
        $page->delete();
        $this->app->redirect($this->app->getUrl());
    }

    public function downloadCodeAction()
    {
        $page = $this->createPageFileInstanceFromRequest();
        $index = (int) $_GET['index'];
        $lines = explode("\n", $page->getContent());
        $fileName = pathinfo($page->getName(), PATHINFO_FILENAME);

        $counter = 0;
        $opened = false;
        $codeType = '';
        $code = [];

        foreach ($lines as $line) {
            $spacelessLine = preg_replace('/[\t\s]+/', '', $line);

            if (strpos($spacelessLine, '```') === 0) {
                if ($opened) {
                    $opened = false;

                    // This is what we are looking for
                    if ($index == $counter) {
                        $this->app->response(implode("\n", $code), 'application/octet-stream', 200, [
                            'Content-Type: application/octet-stream',
                            'Content-Transfer-Encoding: Binary',
                            'Content-disposition: attachment; filename="'.$fileName.'.'.$codeType.'"'
                        ]);
                    }

                    $counter++;
                } else {
                    $opened = true;
                    $code = [];
                    $codeType = substr($line, 3);
                    continue;
                }
            }

            if ($opened) {
                $code[] = $line;
            }
        }
    }
}

}




// src/Maki/Controller/ServeResourceController.php



namespace Maki\Controller {

use Maki\Controller;
use Maki\Maki;

/**
 * Serves media resources (css, js).
 *
 * Class ResourceController
 * @package Maki\Controller
 */
class ServeResourceController extends Controller
{
    public static function match(Maki $app)
    {
        if (isset($_GET['resource'])) {
            return 'serve';
        }
    }

    public function isSecured($action)
    {
        return false;
    }

    public function serve()
    {
        $this->app->getThemeManager()->serveResource($_GET['resource']);
    }
}

}




// src/Maki/Controller/ThemeManagerController.php



namespace Maki\Controller {

use Maki\Controller;
use Maki\Maki;

/**
 * Theme manager.
 *
 * @package Maki\Controller
 */
class ThemeManagerController extends Controller
{
    public static function match(Maki $app)
    {
        if (isset($_GET['change_css'])) {
            return 'changeThemeAction';
        }
    }

    public function changeThemeAction()
    {
        // @todo sanitize (check if this stylesheet exist)
        setcookie('theme_css', $_GET['change_css'], time()+(60 * 60 * 24 * 30 * 12), '/');
        $this->app->redirect($this->app->getUrl().$this->app->getCurrentUrl());
    }
}

}




// src/Maki/Controller/UserController.php



namespace Maki\Controller {

use Maki\Controller;
use Maki\Maki;

class UserController extends Controller
{
    public function isSecured($action)
    {
        if (in_array($action, ['loginPageAction', 'authAction'])) {
            return false;
        }

        return true;
    }

    public static function match(Maki $app)
    {
        // Log out action
        if (isset($_GET['logout'])) {
            return 'logoutAction';
        }

        // Authorization request
        if ($_SERVER['REQUEST_METHOD'] == 'POST' and isset($_GET['auth'])) {
            return 'authAction';
        }
    }

    /**
     * This action is dispatched manually, there is no url "login" or something like this.
     */
    public function loginPageAction()
    {
        $this->viewResponse('resources/views/login.php');
    }

    public function logoutAction()
    {
        $this->app->deauthenticate();
        $this->app->redirect($this->app->getUrl());
    }

    public function authAction()
    {
        $username = isset($_POST['username']) ? $_POST['username'] : '';
        $pass = isset($_POST['password']) ? $_POST['password'] : '';
        $remember = isset($_POST['remember']) ? $_POST['remember'] : '0';

        $users = $this->app['users'];

        foreach ($users as $user) {
            if ($user['username'] == $username and $user['password'] == $pass) {
                $this->app->authenticate($username, $remember == '1');
                $this->app->response();
            }
        }

        $this->jsonResponse([
            'error' => 'Invalid username or password.'
        ], 'application/json', 400);
    }
}

}




// src/Maki/Maki.php



namespace Maki {

/**
 * Class Maki
 * @package Maki
 * @todo secure maki.json
 * @todo better css for headers
 * @todo editor look&feel
 * @todo search
 * @todo page renaming
 * @todo who made change on page
 * @todo expandable sections
 * @todo if page has "." (dot) in name there occurs error "No input file specified"
 * @todo "download as file" option for code snippets
 * @todo nav on mobile
 * @todo make nicer error page for "maki.dev/something.php" url.
 */
class Maki extends \Pimple
{
    protected $url;

    protected $sessionId;
    /**
     * @var ThemeManager
     */
    protected $themeManager;

    /**
     * Base container values.
     * @var array
     */
    protected $values = [
        'main_title'    => null
    ];

    protected $controllers = [
        'Maki\Controller\ServeResourceController' => 9999,
        'Maki\Controller\UserController' => 1000,
        'Maki\Controller\ThemeManagerController' => 1000,
        'Maki\Controller\PageController' => 0
    ];

    /**
     * Config:
     *
     * - docroot - Document root path (must ends with trailing slash)
     *
     * @param array $config
     * @throws \InvalidArgumentException
     */
    public function __construct(array $config = array())
    {
        session_start();
        $this->sessionId = session_id();

        $config = new Collection($config);

        // Document root path must be defined
        if ( ! $config->has('docroot')) {
            throw new \InvalidArgumentException('`docroot` is not defined.');
        }

        $this['docroot'] = $config->pull('docroot');

        // Base url
        $this['url.base'] = $config->pull('url.base') ?: pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME);
        $this['url.base'] = str_replace('//', '/', '/'.trim($this['url.base'], '/').'/');

        // Create htaccess as soon as possible (if needed)
        $this->createHtAccess();
        $this->initThemeManager($config);

        // Documentation files extensions
        $this['docs.extensions'] = $config->pull('docs.extensions') ?: [
            'md'        => 'markdown',
            'markdown'  => 'markdown'
        ];

        $this['cookie.auth_name'] = $config->pull('cookie.auth_name', 'maki');
        $this['cookie.auth_expire'] = $config->pull('cookie.auth_expire', 3600 * 24 * 30); // 30 days
        $this['users'] = $config->pull('users', []);
        $this['salt'] = $config->pull('salt', '');

        $this->values = array_merge($this->values, $config->toArray());

        $this['user'] = null;

        // Define default markdown parser
        if ( ! $this->offsetExists('parser.markdown')) {
            $this['parser.markdown'] = $this->share(function($c) {
                $markdown = new Markdown();
                $markdown->baseUrl = $c['url.base'];

                return $markdown;
            });
        }

        //
        if ( ! $this->offsetExists('docs.path')) {
            $this['docs.path'] = '';
        }

        // Markdown files directory
        $this['docs.path'] = $this['docs.path'] == '' ? '' : rtrim($this['docs.path'], '/').'/';

        // Index file in directory
        if ( ! $this->offsetExists('docs.index_filename')) {
            $this['docs.index_filename'] = 'index';
        }

        // Sidebar filename
        if ( ! $this->offsetExists('docs.navigation_filename')) {
            $this['docs.navigation_filename'] = '_nav';
        }

        if ( ! $this->offsetExists('editable')) {
            $this['editable'] = true;
        }

        // Whats for is "viewable"?
        if ( ! $this->offsetExists('viewable')) {
            $this['viewable'] = true;
        }

        if ( ! $this->offsetExists('cache_dir')) {
            $this['cache_dir'] = '_maki_cache';
        }

        // Normalize path
        $this['cache_dir'] = rtrim($this['cache_dir'], '/').'/';

        // Create cache dir
        if ( ! is_dir($this->getCacheDirAbsPath())) {
            mkdir($this->getCacheDirAbsPath(), 0700, true);
        }

        $this->handleRequest();
    }

    /**
     * @return ThemeManager
     */
    public function getThemeManager()
    {
        return $this->themeManager;
    }

    public function getResource($path)
    {
        $func = 'resource_'.md5($path);

        if (function_exists($func)) {
            return $func();
        }

        $realpath = realpath($this['docroot'].$path);

        if ($realpath == false or strpos($realpath, $this['docroot']) !== 0) {
            return false;
        }

        $ext = pathinfo($realpath, PATHINFO_EXTENSION);

        if ( ! in_array($ext, ['css', 'js'])) {
            return false;
        }

        if (is_file($realpath)) {
            return file_get_contents($realpath);
        }

        return false;
    }

    public function response($body = '', $type = 'text/html', $code = 200, $headers = [])
    {
        switch ($code) {
            case 200: header('HTTP/1.1 200 OK'); break;
            case 400: header('HTTP/1.1 400 Bad Request'); break;
            case 404: header('HTTP/1.1 404 Not Found'); break;
        }

        header('Content-Type: '.$type);

        foreach ($headers as $header) {
            header($header);
        }

        echo $body;

        exit(0);
    }

    public function responseFileNotFound($text = 'File not found')
    {
        $this->response($text, 'text/plain', 404);
    }

    public function getCurrentUrl()
    {
        if ($this->url === null) {
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $script = trim($this['url.base'], '/');
            $this->url = trim(str_replace($script, '', trim($uri, '/')), '/');
        }

        return $this->url;
    }

    /**
     * Return url.
     * @return string
     */
    public function getUrl()
    {
        static $url;

        if (!$url) {
            $ssl = (!empty($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on');
            $protocol = 'http' . ($ssl ? 's' : '');
            $port = $_SERVER['SERVER_PORT'];
            $port = ((!$ssl && $port == '80') || ($ssl && $port == '443')) ? '' : ':' . $port;
            $host = $_SERVER['HTTP_HOST'];

            $url = $protocol . '://' . $host . $port . $this['url.base'];
        }

        return $url;
    }

    public function getCacheDirAbsPath()
    {
        return $this['docroot'].$this['cache_dir'];
    }

    public function getSessionId()
    {
        return $this->sessionId;
    }

    public function redirect($url, $permanent = false)
    {
        if ($permanent) {
            header('HTTP/1.1 301 Moved Permanently');
        } else {
            header('HTTP/1.1 302 Moved Temporarily');
        }

        header('Location: '.$url);
        exit(0);
    }

    /**
     * Return url to specified resource.
     *
     *     $app->getResourceUrl('resources/jquery.js');
     *     // Return "http://domain.com?resource=resources/jquery.js
     *
     * @param $resource
     * @return string
     */
    public function getResourceUrl($resource)
    {
        return $this->getUrl().'?resource='.$resource;
    }

    /**
     * Render view.
     *
     * @param $path Path to view (relative from document root).
     * @param array $data Data passed to view.
     * @return string
     */
    public function render($path, array $data = [])
    {
        $data['app'] = $this;
        $func = 'view_'.md5($path);

        if (function_exists($func)) {
            $content = $func($data);
        } else {
            extract($data);
            $path = $this['docroot'] . $path;

            if (!is_file($path)) {
                throw new \InvalidArgumentException(sprintf('View "%s" does not exists.', $path));
            }

            ob_start();
            include $path;
            $content = ob_get_contents();
            ob_end_clean();
        }

        return $content;
    }

    /**
     * Checks if user is authenticated.
     *
     * @return bool
     */
    public function isUserAuthenticated()
    {
        return isset($_SESSION['auth']);
    }

    /**
     * Return user data for specified username.
     *
     * @param $username User name.
     * @return array User data.
     * @return \InvalidArgumentException If user with specified username does not exists.
     */
    public function getUser($username)
    {
        foreach ($this['users'] as $user) {
            if ($user['username'] === $username) {
                return $user;
            }
        }

        return new \InvalidArgumentException(sprintf('User "%s" does not exists.', $username));
    }

    /**
     * Authenticate user.
     *
     * @param $username User name.
     * @param bool|false $remember Remember user.
     */
    public function authenticate($username, $remember = false)
    {
        $_SESSION['auth'] = $username;

        if ($remember) {
            $token = sha1($username.$this['salt']);
            setcookie($this['cookie.auth_name'], $token, time() + $this['cookie.auth_expire'], '/');

            $path = $this['cache_dir'].'users/';
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }

            file_put_contents($path.$token, $username);

            // Garbage collector
            foreach (scandir($path) as $fileName) {
                if ($fileName == '.' or $fileName == '..') {
                    continue;
                }

                if (filemtime($path.$fileName) < time() - $this['cookie.auth_expire']) {
                    @unlink($path.$fileName);
                }
            }
        }
    }

    /**
     * Deauthenticate user, destroys session, removes "remember me" cookies.
     */
    public function deauthenticate()
    {
        session_destroy();
        unset($_COOKIE[$this['cookie.auth_name']]);
        setcookie($this['cookie.auth_name'], null, -1, '/');
    }

    protected function handleRequest()
    {
        foreach ($this->controllers as $class => $priority) {
            $action = forward_static_call([$class, 'match'], $this);
            if (is_string($action)) {
                $this->dispatchController($class, $action);
            }
        }
    }

    protected function dispatchController($class, $action)
    {
        $controller = new $class($this);

        if ($controller->isSecured($action)) {
            $this->checkAuthentication();
        }

        if (!method_exists($controller, $action)) {
            throw new \InvalidArgumentException(sprintf('Method "%s" does not exists in "%s" controller.', $action, $class));
        }

        call_user_func([$controller, $action]);
    }

    /**
     * Creates and inits theme manager.
     *
     * - defines default theme
     * - adds themes specified in config
     * - resolve active theme
     *
     * @param Collection $config
     */
    protected function initThemeManager(Collection $config)
    {
        $tm = new ThemeManager($this);

        // Set default theme
        $tm->addStylesheet('light', 'resources/light.css');

        // Add styles defined in config
        if ($config->has('theme.stylesheets')) {
            $tm->addStylesheets($config->pull('theme.stylesheets'));
        }

        // Set active theme
        if (isset($_COOKIE['theme_css']) and $tm->isStylesheetExist($_COOKIE['theme_css'])) {
            $tm->setActiveStylesheet($_COOKIE['theme_css']);
        } else if ($config->has('theme.active')) {
            $tm->setActiveStylesheet($config->pull('theme.active'));
        } else {
            $tm->setActiveStylesheet('light');
        }

        $this->themeManager = $tm;
    }

    /**
     * Creates .htaccess file inside root directory.
     */
    protected function createHtAccess()
    {
        // Create htaccess if not exists yet
        if ( ! is_file($this['docroot'].'.htaccess')) {
            file_put_contents($this['docroot'].'.htaccess', '<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews
    </IfModule>

    RewriteEngine On
    RewriteBase '.$this['url.base'].'

    # Redirect Trailing Slashes...
    RewriteRule ^(.*)/$ /$1 [L,R=301]

    # Handle Front Controller...
    # RewriteCond %{REQUEST_FILENAME} !-d
    # RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>');

            $this->redirect($this->getUrl(), false);
        }

    }

    /**
     * Check if user is authenticated
     */
    protected function checkAuthentication()
    {
        // If no users defined wiki is public
        if (!$this['users']) {
            return;
        }

        // User authorized
        if ($this->isUserAuthenticated()) {
            try {
                $this['user'] = $this->getUser($_SESSION['auth']);
                return;
            } catch (\InvalidArgumentException $e) {
                // If user not found on the list it means he/she was logged
                // but in the meantime someone modified maki's config file.
                // We logout this user now.
                $this->deauthenticate();
            }
        }

        $cookieName = $this['cookie.auth_name'];

        // Check if user was remembered
        if (isset($_COOKIE[$cookieName])) {
            $token = $_COOKIE[$cookieName];

            if (strlen($token) == 40 and preg_match('/^[0-9a-z]+$/', $token)) {
                $path = $this['cache_dir'].'users/'.$token;

                if (is_file($path)) {
                    $username = file_get_contents($path);

                    try {
                        // We call this method only to make sure
                        // username from cookie exists in our database.
                        $this->getUser($username);
                        $this->authenticate($username, true);
                        return;
                    } catch (\InvalidArgumentException $e) {
                        // If getUser throw exception login view will be displayed
                    }
                }
            }
        }

        // Display username form
        $this->dispatchController('Maki\Controller\UserController', 'loginPageAction');
    }

}

}




// index.php



namespace {

    

    error_reporting(E_ALL);
    ini_set('display_errors', 'On');

    $config = [];
    $dir = __DIR__.DIRECTORY_SEPARATOR;

    // Load configuration file
    if (is_file($dir.'maki.json')) {
        $config = (array) json_decode(file_get_contents($dir.'maki.json'), true);
    }

    $config['docroot'] = $dir;

    new \Maki\Maki($config);

}


namespace {

function resource_2e26e3325885ef587bc1b25394826717() {
    ob_start(); ?>
    // CodeMirror, copyright (c) by Marijn Haverbeke and others
// Distributed under an MIT license: http://codemirror.net/LICENSE

(function(mod) {
    if (typeof exports == "object" && typeof module == "object") // CommonJS
        mod(require("../../lib/codemirror"));
    else if (typeof define == "function" && define.amd) // AMD
        define(["../../lib/codemirror"], mod);
    else // Plain browser env
        mod(CodeMirror);
})(function(CodeMirror) {
    "use strict";

    CodeMirror.defineOption("rulers", false, function(cm, val) {
        if (cm.state.rulerDiv) {
            cm.display.lineSpace.removeChild(cm.state.rulerDiv)
            cm.state.rulerDiv = null
            cm.off("refresh", drawRulers)
        }
        if (val && val.length) {
            cm.state.rulerDiv = cm.display.lineSpace.insertBefore(document.createElement("div"), cm.display.cursorDiv)
            cm.state.rulerDiv.className = "CodeMirror-rulers"
            drawRulers(cm)
            cm.on("refresh", drawRulers)
        }
    });

    function drawRulers(cm) {
        cm.state.rulerDiv.textContent = ""
        var val = cm.getOption("rulers");
        var cw = cm.defaultCharWidth();
        var left = cm.charCoords(CodeMirror.Pos(cm.firstLine(), 0), "div").left;
        cm.state.rulerDiv.style.minHeight = (cm.display.scroller.offsetHeight + 30) + "px";
        for (var i = 0; i < val.length; i++) {
            var elt = document.createElement("div");
            elt.className = "CodeMirror-ruler";
            var col, conf = val[i];
            if (typeof conf == "number") {
                col = conf;
            } else {
                col = conf.column;
                if (conf.className) elt.className += " " + conf.className;
                if (conf.color) elt.style.borderColor = conf.color;
                if (conf.lineStyle) elt.style.borderLeftStyle = conf.lineStyle;
                if (conf.width) elt.style.borderLeftWidth = conf.width;
            }
            elt.style.left = (left + col * cw) + "px";
            cm.state.rulerDiv.appendChild(elt)
        }
    }
});
    <?php
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}


function resource_d05215a841331ea964a203916f1f5e4f() {
    ob_start(); ?>
    // CodeMirror, copyright (c) by Marijn Haverbeke and others
// Distributed under an MIT license: http://codemirror.net/LICENSE

// This is CodeMirror (http://codemirror.net), a code editor
// implemented in JavaScript on top of the browser's DOM.
//
// You can find some technical background for some of the code below
// at http://marijnhaverbeke.nl/blog/#cm-internals .

(function (global, factory) {
    typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
        typeof define === 'function' && define.amd ? define(factory) :
            (global.CodeMirror = factory());
}(this, (function () { 'use strict';

// Kludges for bugs and behavior differences that can't be feature
// detected are enabled based on userAgent etc sniffing.
    var userAgent = navigator.userAgent
    var platform = navigator.platform

    var gecko = /gecko\/\d/i.test(userAgent)
    var ie_upto10 = /MSIE \d/.test(userAgent)
    var ie_11up = /Trident\/(?:[7-9]|\d{2,})\..*rv:(\d+)/.exec(userAgent)
    var ie = ie_upto10 || ie_11up
    var ie_version = ie && (ie_upto10 ? document.documentMode || 6 : ie_11up[1])
    var webkit = /WebKit\//.test(userAgent)
    var qtwebkit = webkit && /Qt\/\d+\.\d+/.test(userAgent)
    var chrome = /Chrome\//.test(userAgent)
    var presto = /Opera\//.test(userAgent)
    var safari = /Apple Computer/.test(navigator.vendor)
    var mac_geMountainLion = /Mac OS X 1\d\D([8-9]|\d\d)\D/.test(userAgent)
    var phantom = /PhantomJS/.test(userAgent)

    var ios = /AppleWebKit/.test(userAgent) && /Mobile\/\w+/.test(userAgent)
// This is woefully incomplete. Suggestions for alternative methods welcome.
    var mobile = ios || /Android|webOS|BlackBerry|Opera Mini|Opera Mobi|IEMobile/i.test(userAgent)
    var mac = ios || /Mac/.test(platform)
    var chromeOS = /\bCrOS\b/.test(userAgent)
    var windows = /win/i.test(platform)

    var presto_version = presto && userAgent.match(/Version\/(\d*\.\d*)/)
    if (presto_version) { presto_version = Number(presto_version[1]) }
    if (presto_version && presto_version >= 15) { presto = false; webkit = true }
// Some browsers use the wrong event properties to signal cmd/ctrl on OS X
    var flipCtrlCmd = mac && (qtwebkit || presto && (presto_version == null || presto_version < 12.11))
    var captureRightClick = gecko || (ie && ie_version >= 9)

    function classTest(cls) { return new RegExp("(^|\\s)" + cls + "(?:$|\\s)\\s*") }

    var rmClass = function(node, cls) {
        var current = node.className
        var match = classTest(cls).exec(current)
        if (match) {
            var after = current.slice(match.index + match[0].length)
            node.className = current.slice(0, match.index) + (after ? match[1] + after : "")
        }
    }

    function removeChildren(e) {
        for (var count = e.childNodes.length; count > 0; --count)
        { e.removeChild(e.firstChild) }
        return e
    }

    function removeChildrenAndAdd(parent, e) {
        return removeChildren(parent).appendChild(e)
    }

    function elt(tag, content, className, style) {
        var e = document.createElement(tag)
        if (className) { e.className = className }
        if (style) { e.style.cssText = style }
        if (typeof content == "string") { e.appendChild(document.createTextNode(content)) }
        else if (content) { for (var i = 0; i < content.length; ++i) { e.appendChild(content[i]) } }
        return e
    }

    var range
    if (document.createRange) { range = function(node, start, end, endNode) {
        var r = document.createRange()
        r.setEnd(endNode || node, end)
        r.setStart(node, start)
        return r
    } }
    else { range = function(node, start, end) {
        var r = document.body.createTextRange()
        try { r.moveToElementText(node.parentNode) }
        catch(e) { return r }
        r.collapse(true)
        r.moveEnd("character", end)
        r.moveStart("character", start)
        return r
    } }

    function contains(parent, child) {
        if (child.nodeType == 3) // Android browser always returns false when child is a textnode
        { child = child.parentNode }
        if (parent.contains)
        { return parent.contains(child) }
        do {
            if (child.nodeType == 11) { child = child.host }
            if (child == parent) { return true }
        } while (child = child.parentNode)
    }

    var activeElt = function() {
        var activeElement = document.activeElement
        while (activeElement && activeElement.root && activeElement.root.activeElement)
        { activeElement = activeElement.root.activeElement }
        return activeElement
    }
// Older versions of IE throws unspecified error when touching
// document.activeElement in some cases (during loading, in iframe)
    if (ie && ie_version < 11) { activeElt = function() {
        try { return document.activeElement }
        catch(e) { return document.body }
    } }

    function addClass(node, cls) {
        var current = node.className
        if (!classTest(cls).test(current)) { node.className += (current ? " " : "") + cls }
    }
    function joinClasses(a, b) {
        var as = a.split(" ")
        for (var i = 0; i < as.length; i++)
        { if (as[i] && !classTest(as[i]).test(b)) { b += " " + as[i] } }
        return b
    }

    var selectInput = function(node) { node.select() }
    if (ios) // Mobile Safari apparently has a bug where select() is broken.
    { selectInput = function(node) { node.selectionStart = 0; node.selectionEnd = node.value.length } }
    else if (ie) // Suppress mysterious IE10 errors
    { selectInput = function(node) { try { node.select() } catch(_e) {} } }

    function bind(f) {
        var args = Array.prototype.slice.call(arguments, 1)
        return function(){return f.apply(null, args)}
    }

    function copyObj(obj, target, overwrite) {
        if (!target) { target = {} }
        for (var prop in obj)
        { if (obj.hasOwnProperty(prop) && (overwrite !== false || !target.hasOwnProperty(prop)))
        { target[prop] = obj[prop] } }
        return target
    }

// Counts the column offset in a string, taking tabs into account.
// Used mostly to find indentation.
    function countColumn(string, end, tabSize, startIndex, startValue) {
        if (end == null) {
            end = string.search(/[^\s\u00a0]/)
            if (end == -1) { end = string.length }
        }
        for (var i = startIndex || 0, n = startValue || 0;;) {
            var nextTab = string.indexOf("\t", i)
            if (nextTab < 0 || nextTab >= end)
            { return n + (end - i) }
            n += nextTab - i
            n += tabSize - (n % tabSize)
            i = nextTab + 1
        }
    }

    function Delayed() {this.id = null}
    Delayed.prototype.set = function(ms, f) {
        clearTimeout(this.id)
        this.id = setTimeout(f, ms)
    }

    function indexOf(array, elt) {
        for (var i = 0; i < array.length; ++i)
        { if (array[i] == elt) { return i } }
        return -1
    }

// Number of pixels added to scroller and sizer to hide scrollbar
    var scrollerGap = 30

// Returned or thrown by various protocols to signal 'I'm not
// handling this'.
    var Pass = {toString: function(){return "CodeMirror.Pass"}}

// Reused option objects for setSelection & friends
    var sel_dontScroll = {scroll: false};
    var sel_mouse = {origin: "*mouse"};
    var sel_move = {origin: "+move"}

// The inverse of countColumn -- find the offset that corresponds to
// a particular column.
    function findColumn(string, goal, tabSize) {
        for (var pos = 0, col = 0;;) {
            var nextTab = string.indexOf("\t", pos)
            if (nextTab == -1) { nextTab = string.length }
            var skipped = nextTab - pos
            if (nextTab == string.length || col + skipped >= goal)
            { return pos + Math.min(skipped, goal - col) }
            col += nextTab - pos
            col += tabSize - (col % tabSize)
            pos = nextTab + 1
            if (col >= goal) { return pos }
        }
    }

    var spaceStrs = [""]
    function spaceStr(n) {
        while (spaceStrs.length <= n)
        { spaceStrs.push(lst(spaceStrs) + " ") }
        return spaceStrs[n]
    }

    function lst(arr) { return arr[arr.length-1] }

    function map(array, f) {
        var out = []
        for (var i = 0; i < array.length; i++) { out[i] = f(array[i], i) }
        return out
    }

    function insertSorted(array, value, score) {
        var pos = 0, priority = score(value)
        while (pos < array.length && score(array[pos]) <= priority) { pos++ }
        array.splice(pos, 0, value)
    }

    function nothing() {}

    function createObj(base, props) {
        var inst
        if (Object.create) {
            inst = Object.create(base)
        } else {
            nothing.prototype = base
            inst = new nothing()
        }
        if (props) { copyObj(props, inst) }
        return inst
    }

    var nonASCIISingleCaseWordChar = /[\u00df\u0587\u0590-\u05f4\u0600-\u06ff\u3040-\u309f\u30a0-\u30ff\u3400-\u4db5\u4e00-\u9fcc\uac00-\ud7af]/
    function isWordCharBasic(ch) {
        return /\w/.test(ch) || ch > "\x80" &&
            (ch.toUpperCase() != ch.toLowerCase() || nonASCIISingleCaseWordChar.test(ch))
    }
    function isWordChar(ch, helper) {
        if (!helper) { return isWordCharBasic(ch) }
        if (helper.source.indexOf("\\w") > -1 && isWordCharBasic(ch)) { return true }
        return helper.test(ch)
    }

    function isEmpty(obj) {
        for (var n in obj) { if (obj.hasOwnProperty(n) && obj[n]) { return false } }
        return true
    }

// Extending unicode characters. A series of a non-extending char +
// any number of extending chars is treated as a single unit as far
// as editing and measuring is concerned. This is not fully correct,
// since some scripts/fonts/browsers also treat other configurations
// of code points as a group.
    var extendingChars = /[\u0300-\u036f\u0483-\u0489\u0591-\u05bd\u05bf\u05c1\u05c2\u05c4\u05c5\u05c7\u0610-\u061a\u064b-\u065e\u0670\u06d6-\u06dc\u06de-\u06e4\u06e7\u06e8\u06ea-\u06ed\u0711\u0730-\u074a\u07a6-\u07b0\u07eb-\u07f3\u0816-\u0819\u081b-\u0823\u0825-\u0827\u0829-\u082d\u0900-\u0902\u093c\u0941-\u0948\u094d\u0951-\u0955\u0962\u0963\u0981\u09bc\u09be\u09c1-\u09c4\u09cd\u09d7\u09e2\u09e3\u0a01\u0a02\u0a3c\u0a41\u0a42\u0a47\u0a48\u0a4b-\u0a4d\u0a51\u0a70\u0a71\u0a75\u0a81\u0a82\u0abc\u0ac1-\u0ac5\u0ac7\u0ac8\u0acd\u0ae2\u0ae3\u0b01\u0b3c\u0b3e\u0b3f\u0b41-\u0b44\u0b4d\u0b56\u0b57\u0b62\u0b63\u0b82\u0bbe\u0bc0\u0bcd\u0bd7\u0c3e-\u0c40\u0c46-\u0c48\u0c4a-\u0c4d\u0c55\u0c56\u0c62\u0c63\u0cbc\u0cbf\u0cc2\u0cc6\u0ccc\u0ccd\u0cd5\u0cd6\u0ce2\u0ce3\u0d3e\u0d41-\u0d44\u0d4d\u0d57\u0d62\u0d63\u0dca\u0dcf\u0dd2-\u0dd4\u0dd6\u0ddf\u0e31\u0e34-\u0e3a\u0e47-\u0e4e\u0eb1\u0eb4-\u0eb9\u0ebb\u0ebc\u0ec8-\u0ecd\u0f18\u0f19\u0f35\u0f37\u0f39\u0f71-\u0f7e\u0f80-\u0f84\u0f86\u0f87\u0f90-\u0f97\u0f99-\u0fbc\u0fc6\u102d-\u1030\u1032-\u1037\u1039\u103a\u103d\u103e\u1058\u1059\u105e-\u1060\u1071-\u1074\u1082\u1085\u1086\u108d\u109d\u135f\u1712-\u1714\u1732-\u1734\u1752\u1753\u1772\u1773\u17b7-\u17bd\u17c6\u17c9-\u17d3\u17dd\u180b-\u180d\u18a9\u1920-\u1922\u1927\u1928\u1932\u1939-\u193b\u1a17\u1a18\u1a56\u1a58-\u1a5e\u1a60\u1a62\u1a65-\u1a6c\u1a73-\u1a7c\u1a7f\u1b00-\u1b03\u1b34\u1b36-\u1b3a\u1b3c\u1b42\u1b6b-\u1b73\u1b80\u1b81\u1ba2-\u1ba5\u1ba8\u1ba9\u1c2c-\u1c33\u1c36\u1c37\u1cd0-\u1cd2\u1cd4-\u1ce0\u1ce2-\u1ce8\u1ced\u1dc0-\u1de6\u1dfd-\u1dff\u200c\u200d\u20d0-\u20f0\u2cef-\u2cf1\u2de0-\u2dff\u302a-\u302f\u3099\u309a\ua66f-\ua672\ua67c\ua67d\ua6f0\ua6f1\ua802\ua806\ua80b\ua825\ua826\ua8c4\ua8e0-\ua8f1\ua926-\ua92d\ua947-\ua951\ua980-\ua982\ua9b3\ua9b6-\ua9b9\ua9bc\uaa29-\uaa2e\uaa31\uaa32\uaa35\uaa36\uaa43\uaa4c\uaab0\uaab2-\uaab4\uaab7\uaab8\uaabe\uaabf\uaac1\uabe5\uabe8\uabed\udc00-\udfff\ufb1e\ufe00-\ufe0f\ufe20-\ufe26\uff9e\uff9f]/
    function isExtendingChar(ch) { return ch.charCodeAt(0) >= 768 && extendingChars.test(ch) }

// The display handles the DOM integration, both for input reading
// and content drawing. It holds references to DOM nodes and
// display-related state.

    function Display(place, doc, input) {
        var d = this
        this.input = input

        // Covers bottom-right square when both scrollbars are present.
        d.scrollbarFiller = elt("div", null, "CodeMirror-scrollbar-filler")
        d.scrollbarFiller.setAttribute("cm-not-content", "true")
        // Covers bottom of gutter when coverGutterNextToScrollbar is on
        // and h scrollbar is present.
        d.gutterFiller = elt("div", null, "CodeMirror-gutter-filler")
        d.gutterFiller.setAttribute("cm-not-content", "true")
        // Will contain the actual code, positioned to cover the viewport.
        d.lineDiv = elt("div", null, "CodeMirror-code")
        // Elements are added to these to represent selection and cursors.
        d.selectionDiv = elt("div", null, null, "position: relative; z-index: 1")
        d.cursorDiv = elt("div", null, "CodeMirror-cursors")
        // A visibility: hidden element used to find the size of things.
        d.measure = elt("div", null, "CodeMirror-measure")
        // When lines outside of the viewport are measured, they are drawn in this.
        d.lineMeasure = elt("div", null, "CodeMirror-measure")
        // Wraps everything that needs to exist inside the vertically-padded coordinate system
        d.lineSpace = elt("div", [d.measure, d.lineMeasure, d.selectionDiv, d.cursorDiv, d.lineDiv],
            null, "position: relative; outline: none")
        // Moved around its parent to cover visible view.
        d.mover = elt("div", [elt("div", [d.lineSpace], "CodeMirror-lines")], null, "position: relative")
        // Set to the height of the document, allowing scrolling.
        d.sizer = elt("div", [d.mover], "CodeMirror-sizer")
        d.sizerWidth = null
        // Behavior of elts with overflow: auto and padding is
        // inconsistent across browsers. This is used to ensure the
        // scrollable area is big enough.
        d.heightForcer = elt("div", null, null, "position: absolute; height: " + scrollerGap + "px; width: 1px;")
        // Will contain the gutters, if any.
        d.gutters = elt("div", null, "CodeMirror-gutters")
        d.lineGutter = null
        // Actual scrollable element.
        d.scroller = elt("div", [d.sizer, d.heightForcer, d.gutters], "CodeMirror-scroll")
        d.scroller.setAttribute("tabIndex", "-1")
        // The element in which the editor lives.
        d.wrapper = elt("div", [d.scrollbarFiller, d.gutterFiller, d.scroller], "CodeMirror")

        // Work around IE7 z-index bug (not perfect, hence IE7 not really being supported)
        if (ie && ie_version < 8) { d.gutters.style.zIndex = -1; d.scroller.style.paddingRight = 0 }
        if (!webkit && !(gecko && mobile)) { d.scroller.draggable = true }

        if (place) {
            if (place.appendChild) { place.appendChild(d.wrapper) }
            else { place(d.wrapper) }
        }

        // Current rendered range (may be bigger than the view window).
        d.viewFrom = d.viewTo = doc.first
        d.reportedViewFrom = d.reportedViewTo = doc.first
        // Information about the rendered lines.
        d.view = []
        d.renderedView = null
        // Holds info about a single rendered line when it was rendered
        // for measurement, while not in view.
        d.externalMeasured = null
        // Empty space (in pixels) above the view
        d.viewOffset = 0
        d.lastWrapHeight = d.lastWrapWidth = 0
        d.updateLineNumbers = null

        d.nativeBarWidth = d.barHeight = d.barWidth = 0
        d.scrollbarsClipped = false

        // Used to only resize the line number gutter when necessary (when
        // the amount of lines crosses a boundary that makes its width change)
        d.lineNumWidth = d.lineNumInnerWidth = d.lineNumChars = null
        // Set to true when a non-horizontal-scrolling line widget is
        // added. As an optimization, line widget aligning is skipped when
        // this is false.
        d.alignWidgets = false

        d.cachedCharWidth = d.cachedTextHeight = d.cachedPaddingH = null

        // Tracks the maximum line length so that the horizontal scrollbar
        // can be kept static when scrolling.
        d.maxLine = null
        d.maxLineLength = 0
        d.maxLineChanged = false

        // Used for measuring wheel scrolling granularity
        d.wheelDX = d.wheelDY = d.wheelStartX = d.wheelStartY = null

        // True when shift is held down.
        d.shift = false

        // Used to track whether anything happened since the context menu
        // was opened.
        d.selForContextMenu = null

        d.activeTouch = null

        input.init(d)
    }

// Find the line object corresponding to the given line number.
    function getLine(doc, n) {
        n -= doc.first
        if (n < 0 || n >= doc.size) { throw new Error("There is no line " + (n + doc.first) + " in the document.") }
        var chunk = doc
        while (!chunk.lines) {
            for (var i = 0;; ++i) {
                var child = chunk.children[i], sz = child.chunkSize()
                if (n < sz) { chunk = child; break }
                n -= sz
            }
        }
        return chunk.lines[n]
    }

// Get the part of a document between two positions, as an array of
// strings.
    function getBetween(doc, start, end) {
        var out = [], n = start.line
        doc.iter(start.line, end.line + 1, function (line) {
            var text = line.text
            if (n == end.line) { text = text.slice(0, end.ch) }
            if (n == start.line) { text = text.slice(start.ch) }
            out.push(text)
            ++n
        })
        return out
    }
// Get the lines between from and to, as array of strings.
    function getLines(doc, from, to) {
        var out = []
        doc.iter(from, to, function (line) { out.push(line.text) }) // iter aborts when callback returns truthy value
        return out
    }

// Update the height of a line, propagating the height change
// upwards to parent nodes.
    function updateLineHeight(line, height) {
        var diff = height - line.height
        if (diff) { for (var n = line; n; n = n.parent) { n.height += diff } }
    }

// Given a line object, find its line number by walking up through
// its parent links.
    function lineNo(line) {
        if (line.parent == null) { return null }
        var cur = line.parent, no = indexOf(cur.lines, line)
        for (var chunk = cur.parent; chunk; cur = chunk, chunk = chunk.parent) {
            for (var i = 0;; ++i) {
                if (chunk.children[i] == cur) { break }
                no += chunk.children[i].chunkSize()
            }
        }
        return no + cur.first
    }

// Find the line at the given vertical position, using the height
// information in the document tree.
    function lineAtHeight(chunk, h) {
        var n = chunk.first
        outer: do {
            for (var i$1 = 0; i$1 < chunk.children.length; ++i$1) {
                var child = chunk.children[i$1], ch = child.height
                if (h < ch) { chunk = child; continue outer }
                h -= ch
                n += child.chunkSize()
            }
            return n
        } while (!chunk.lines)
        var i = 0
        for (; i < chunk.lines.length; ++i) {
            var line = chunk.lines[i], lh = line.height
            if (h < lh) { break }
            h -= lh
        }
        return n + i
    }

    function isLine(doc, l) {return l >= doc.first && l < doc.first + doc.size}

    function lineNumberFor(options, i) {
        return String(options.lineNumberFormatter(i + options.firstLineNumber))
    }

// A Pos instance represents a position within the text.
    function Pos (line, ch) {
        if (!(this instanceof Pos)) { return new Pos(line, ch) }
        this.line = line; this.ch = ch
    }

// Compare two positions, return 0 if they are the same, a negative
// number when a is less, and a positive number otherwise.
    function cmp(a, b) { return a.line - b.line || a.ch - b.ch }

    function copyPos(x) {return Pos(x.line, x.ch)}
    function maxPos(a, b) { return cmp(a, b) < 0 ? b : a }
    function minPos(a, b) { return cmp(a, b) < 0 ? a : b }

// Most of the external API clips given positions to make sure they
// actually exist within the document.
    function clipLine(doc, n) {return Math.max(doc.first, Math.min(n, doc.first + doc.size - 1))}
    function clipPos(doc, pos) {
        if (pos.line < doc.first) { return Pos(doc.first, 0) }
        var last = doc.first + doc.size - 1
        if (pos.line > last) { return Pos(last, getLine(doc, last).text.length) }
        return clipToLen(pos, getLine(doc, pos.line).text.length)
    }
    function clipToLen(pos, linelen) {
        var ch = pos.ch
        if (ch == null || ch > linelen) { return Pos(pos.line, linelen) }
        else if (ch < 0) { return Pos(pos.line, 0) }
        else { return pos }
    }
    function clipPosArray(doc, array) {
        var out = []
        for (var i = 0; i < array.length; i++) { out[i] = clipPos(doc, array[i]) }
        return out
    }

// Optimize some code when these features are not used.
    var sawReadOnlySpans = false;
    var sawCollapsedSpans = false

    function seeReadOnlySpans() {
        sawReadOnlySpans = true
    }

    function seeCollapsedSpans() {
        sawCollapsedSpans = true
    }

// TEXTMARKER SPANS

    function MarkedSpan(marker, from, to) {
        this.marker = marker
        this.from = from; this.to = to
    }

// Search an array of spans for a span matching the given marker.
    function getMarkedSpanFor(spans, marker) {
        if (spans) { for (var i = 0; i < spans.length; ++i) {
            var span = spans[i]
            if (span.marker == marker) { return span }
        } }
    }
// Remove a span from an array, returning undefined if no spans are
// left (we don't store arrays for lines without spans).
    function removeMarkedSpan(spans, span) {
        var r
        for (var i = 0; i < spans.length; ++i)
        { if (spans[i] != span) { (r || (r = [])).push(spans[i]) } }
        return r
    }
// Add a span to a line.
    function addMarkedSpan(line, span) {
        line.markedSpans = line.markedSpans ? line.markedSpans.concat([span]) : [span]
        span.marker.attachLine(line)
    }

// Used for the algorithm that adjusts markers for a change in the
// document. These functions cut an array of spans at a given
// character position, returning an array of remaining chunks (or
// undefined if nothing remains).
    function markedSpansBefore(old, startCh, isInsert) {
        var nw
        if (old) { for (var i = 0; i < old.length; ++i) {
            var span = old[i], marker = span.marker
            var startsBefore = span.from == null || (marker.inclusiveLeft ? span.from <= startCh : span.from < startCh)
            if (startsBefore || span.from == startCh && marker.type == "bookmark" && (!isInsert || !span.marker.insertLeft)) {
                var endsAfter = span.to == null || (marker.inclusiveRight ? span.to >= startCh : span.to > startCh);(nw || (nw = [])).push(new MarkedSpan(marker, span.from, endsAfter ? null : span.to))
            }
        } }
        return nw
    }
    function markedSpansAfter(old, endCh, isInsert) {
        var nw
        if (old) { for (var i = 0; i < old.length; ++i) {
            var span = old[i], marker = span.marker
            var endsAfter = span.to == null || (marker.inclusiveRight ? span.to >= endCh : span.to > endCh)
            if (endsAfter || span.from == endCh && marker.type == "bookmark" && (!isInsert || span.marker.insertLeft)) {
                var startsBefore = span.from == null || (marker.inclusiveLeft ? span.from <= endCh : span.from < endCh);(nw || (nw = [])).push(new MarkedSpan(marker, startsBefore ? null : span.from - endCh,
                    span.to == null ? null : span.to - endCh))
            }
        } }
        return nw
    }

// Given a change object, compute the new set of marker spans that
// cover the line in which the change took place. Removes spans
// entirely within the change, reconnects spans belonging to the
// same marker that appear on both sides of the change, and cuts off
// spans partially within the change. Returns an array of span
// arrays with one element for each line in (after) the change.
    function stretchSpansOverChange(doc, change) {
        if (change.full) { return null }
        var oldFirst = isLine(doc, change.from.line) && getLine(doc, change.from.line).markedSpans
        var oldLast = isLine(doc, change.to.line) && getLine(doc, change.to.line).markedSpans
        if (!oldFirst && !oldLast) { return null }

        var startCh = change.from.ch, endCh = change.to.ch, isInsert = cmp(change.from, change.to) == 0
        // Get the spans that 'stick out' on both sides
        var first = markedSpansBefore(oldFirst, startCh, isInsert)
        var last = markedSpansAfter(oldLast, endCh, isInsert)

        // Next, merge those two ends
        var sameLine = change.text.length == 1, offset = lst(change.text).length + (sameLine ? startCh : 0)
        if (first) {
            // Fix up .to properties of first
            for (var i = 0; i < first.length; ++i) {
                var span = first[i]
                if (span.to == null) {
                    var found = getMarkedSpanFor(last, span.marker)
                    if (!found) { span.to = startCh }
                    else if (sameLine) { span.to = found.to == null ? null : found.to + offset }
                }
            }
        }
        if (last) {
            // Fix up .from in last (or move them into first in case of sameLine)
            for (var i$1 = 0; i$1 < last.length; ++i$1) {
                var span$1 = last[i$1]
                if (span$1.to != null) { span$1.to += offset }
                if (span$1.from == null) {
                    var found$1 = getMarkedSpanFor(first, span$1.marker)
                    if (!found$1) {
                        span$1.from = offset
                        if (sameLine) { (first || (first = [])).push(span$1) }
                    }
                } else {
                    span$1.from += offset
                    if (sameLine) { (first || (first = [])).push(span$1) }
                }
            }
        }
        // Make sure we didn't create any zero-length spans
        if (first) { first = clearEmptySpans(first) }
        if (last && last != first) { last = clearEmptySpans(last) }

        var newMarkers = [first]
        if (!sameLine) {
            // Fill gap with whole-line-spans
            var gap = change.text.length - 2, gapMarkers
            if (gap > 0 && first)
            { for (var i$2 = 0; i$2 < first.length; ++i$2)
            { if (first[i$2].to == null)
            { (gapMarkers || (gapMarkers = [])).push(new MarkedSpan(first[i$2].marker, null, null)) } } }
            for (var i$3 = 0; i$3 < gap; ++i$3)
            { newMarkers.push(gapMarkers) }
            newMarkers.push(last)
        }
        return newMarkers
    }

// Remove spans that are empty and don't have a clearWhenEmpty
// option of false.
    function clearEmptySpans(spans) {
        for (var i = 0; i < spans.length; ++i) {
            var span = spans[i]
            if (span.from != null && span.from == span.to && span.marker.clearWhenEmpty !== false)
            { spans.splice(i--, 1) }
        }
        if (!spans.length) { return null }
        return spans
    }

// Used to 'clip' out readOnly ranges when making a change.
    function removeReadOnlyRanges(doc, from, to) {
        var markers = null
        doc.iter(from.line, to.line + 1, function (line) {
            if (line.markedSpans) { for (var i = 0; i < line.markedSpans.length; ++i) {
                var mark = line.markedSpans[i].marker
                if (mark.readOnly && (!markers || indexOf(markers, mark) == -1))
                { (markers || (markers = [])).push(mark) }
            } }
        })
        if (!markers) { return null }
        var parts = [{from: from, to: to}]
        for (var i = 0; i < markers.length; ++i) {
            var mk = markers[i], m = mk.find(0)
            for (var j = 0; j < parts.length; ++j) {
                var p = parts[j]
                if (cmp(p.to, m.from) < 0 || cmp(p.from, m.to) > 0) { continue }
                var newParts = [j, 1], dfrom = cmp(p.from, m.from), dto = cmp(p.to, m.to)
                if (dfrom < 0 || !mk.inclusiveLeft && !dfrom)
                { newParts.push({from: p.from, to: m.from}) }
                if (dto > 0 || !mk.inclusiveRight && !dto)
                { newParts.push({from: m.to, to: p.to}) }
                parts.splice.apply(parts, newParts)
                j += newParts.length - 1
            }
        }
        return parts
    }

// Connect or disconnect spans from a line.
    function detachMarkedSpans(line) {
        var spans = line.markedSpans
        if (!spans) { return }
        for (var i = 0; i < spans.length; ++i)
        { spans[i].marker.detachLine(line) }
        line.markedSpans = null
    }
    function attachMarkedSpans(line, spans) {
        if (!spans) { return }
        for (var i = 0; i < spans.length; ++i)
        { spans[i].marker.attachLine(line) }
        line.markedSpans = spans
    }

// Helpers used when computing which overlapping collapsed span
// counts as the larger one.
    function extraLeft(marker) { return marker.inclusiveLeft ? -1 : 0 }
    function extraRight(marker) { return marker.inclusiveRight ? 1 : 0 }

// Returns a number indicating which of two overlapping collapsed
// spans is larger (and thus includes the other). Falls back to
// comparing ids when the spans cover exactly the same range.
    function compareCollapsedMarkers(a, b) {
        var lenDiff = a.lines.length - b.lines.length
        if (lenDiff != 0) { return lenDiff }
        var aPos = a.find(), bPos = b.find()
        var fromCmp = cmp(aPos.from, bPos.from) || extraLeft(a) - extraLeft(b)
        if (fromCmp) { return -fromCmp }
        var toCmp = cmp(aPos.to, bPos.to) || extraRight(a) - extraRight(b)
        if (toCmp) { return toCmp }
        return b.id - a.id
    }

// Find out whether a line ends or starts in a collapsed span. If
// so, return the marker for that span.
    function collapsedSpanAtSide(line, start) {
        var sps = sawCollapsedSpans && line.markedSpans, found
        if (sps) { for (var sp = void 0, i = 0; i < sps.length; ++i) {
            sp = sps[i]
            if (sp.marker.collapsed && (start ? sp.from : sp.to) == null &&
                (!found || compareCollapsedMarkers(found, sp.marker) < 0))
            { found = sp.marker }
        } }
        return found
    }
    function collapsedSpanAtStart(line) { return collapsedSpanAtSide(line, true) }
    function collapsedSpanAtEnd(line) { return collapsedSpanAtSide(line, false) }

// Test whether there exists a collapsed span that partially
// overlaps (covers the start or end, but not both) of a new span.
// Such overlap is not allowed.
    function conflictingCollapsedRange(doc, lineNo$$1, from, to, marker) {
        var line = getLine(doc, lineNo$$1)
        var sps = sawCollapsedSpans && line.markedSpans
        if (sps) { for (var i = 0; i < sps.length; ++i) {
            var sp = sps[i]
            if (!sp.marker.collapsed) { continue }
            var found = sp.marker.find(0)
            var fromCmp = cmp(found.from, from) || extraLeft(sp.marker) - extraLeft(marker)
            var toCmp = cmp(found.to, to) || extraRight(sp.marker) - extraRight(marker)
            if (fromCmp >= 0 && toCmp <= 0 || fromCmp <= 0 && toCmp >= 0) { continue }
            if (fromCmp <= 0 && (sp.marker.inclusiveRight && marker.inclusiveLeft ? cmp(found.to, from) >= 0 : cmp(found.to, from) > 0) ||
                fromCmp >= 0 && (sp.marker.inclusiveRight && marker.inclusiveLeft ? cmp(found.from, to) <= 0 : cmp(found.from, to) < 0))
            { return true }
        } }
    }

// A visual line is a line as drawn on the screen. Folding, for
// example, can cause multiple logical lines to appear on the same
// visual line. This finds the start of the visual line that the
// given line is part of (usually that is the line itself).
    function visualLine(line) {
        var merged
        while (merged = collapsedSpanAtStart(line))
        { line = merged.find(-1, true).line }
        return line
    }

// Returns an array of logical lines that continue the visual line
// started by the argument, or undefined if there are no such lines.
    function visualLineContinued(line) {
        var merged, lines
        while (merged = collapsedSpanAtEnd(line)) {
            line = merged.find(1, true).line
            ;(lines || (lines = [])).push(line)
        }
        return lines
    }

// Get the line number of the start of the visual line that the
// given line number is part of.
    function visualLineNo(doc, lineN) {
        var line = getLine(doc, lineN), vis = visualLine(line)
        if (line == vis) { return lineN }
        return lineNo(vis)
    }

// Get the line number of the start of the next visual line after
// the given line.
    function visualLineEndNo(doc, lineN) {
        if (lineN > doc.lastLine()) { return lineN }
        var line = getLine(doc, lineN), merged
        if (!lineIsHidden(doc, line)) { return lineN }
        while (merged = collapsedSpanAtEnd(line))
        { line = merged.find(1, true).line }
        return lineNo(line) + 1
    }

// Compute whether a line is hidden. Lines count as hidden when they
// are part of a visual line that starts with another line, or when
// they are entirely covered by collapsed, non-widget span.
    function lineIsHidden(doc, line) {
        var sps = sawCollapsedSpans && line.markedSpans
        if (sps) { for (var sp = void 0, i = 0; i < sps.length; ++i) {
            sp = sps[i]
            if (!sp.marker.collapsed) { continue }
            if (sp.from == null) { return true }
            if (sp.marker.widgetNode) { continue }
            if (sp.from == 0 && sp.marker.inclusiveLeft && lineIsHiddenInner(doc, line, sp))
            { return true }
        } }
    }
    function lineIsHiddenInner(doc, line, span) {
        if (span.to == null) {
            var end = span.marker.find(1, true)
            return lineIsHiddenInner(doc, end.line, getMarkedSpanFor(end.line.markedSpans, span.marker))
        }
        if (span.marker.inclusiveRight && span.to == line.text.length)
        { return true }
        for (var sp = void 0, i = 0; i < line.markedSpans.length; ++i) {
            sp = line.markedSpans[i]
            if (sp.marker.collapsed && !sp.marker.widgetNode && sp.from == span.to &&
                (sp.to == null || sp.to != span.from) &&
                (sp.marker.inclusiveLeft || span.marker.inclusiveRight) &&
                lineIsHiddenInner(doc, line, sp)) { return true }
        }
    }

// Find the height above the given line.
    function heightAtLine(lineObj) {
        lineObj = visualLine(lineObj)

        var h = 0, chunk = lineObj.parent
        for (var i = 0; i < chunk.lines.length; ++i) {
            var line = chunk.lines[i]
            if (line == lineObj) { break }
            else { h += line.height }
        }
        for (var p = chunk.parent; p; chunk = p, p = chunk.parent) {
            for (var i$1 = 0; i$1 < p.children.length; ++i$1) {
                var cur = p.children[i$1]
                if (cur == chunk) { break }
                else { h += cur.height }
            }
        }
        return h
    }

// Compute the character length of a line, taking into account
// collapsed ranges (see markText) that might hide parts, and join
// other lines onto it.
    function lineLength(line) {
        if (line.height == 0) { return 0 }
        var len = line.text.length, merged, cur = line
        while (merged = collapsedSpanAtStart(cur)) {
            var found = merged.find(0, true)
            cur = found.from.line
            len += found.from.ch - found.to.ch
        }
        cur = line
        while (merged = collapsedSpanAtEnd(cur)) {
            var found$1 = merged.find(0, true)
            len -= cur.text.length - found$1.from.ch
            cur = found$1.to.line
            len += cur.text.length - found$1.to.ch
        }
        return len
    }

// Find the longest line in the document.
    function findMaxLine(cm) {
        var d = cm.display, doc = cm.doc
        d.maxLine = getLine(doc, doc.first)
        d.maxLineLength = lineLength(d.maxLine)
        d.maxLineChanged = true
        doc.iter(function (line) {
            var len = lineLength(line)
            if (len > d.maxLineLength) {
                d.maxLineLength = len
                d.maxLine = line
            }
        })
    }

// BIDI HELPERS

    function iterateBidiSections(order, from, to, f) {
        if (!order) { return f(from, to, "ltr") }
        var found = false
        for (var i = 0; i < order.length; ++i) {
            var part = order[i]
            if (part.from < to && part.to > from || from == to && part.to == from) {
                f(Math.max(part.from, from), Math.min(part.to, to), part.level == 1 ? "rtl" : "ltr")
                found = true
            }
        }
        if (!found) { f(from, to, "ltr") }
    }

    function bidiLeft(part) { return part.level % 2 ? part.to : part.from }
    function bidiRight(part) { return part.level % 2 ? part.from : part.to }

    function lineLeft(line) { var order = getOrder(line); return order ? bidiLeft(order[0]) : 0 }
    function lineRight(line) {
        var order = getOrder(line)
        if (!order) { return line.text.length }
        return bidiRight(lst(order))
    }

    function compareBidiLevel(order, a, b) {
        var linedir = order[0].level
        if (a == linedir) { return true }
        if (b == linedir) { return false }
        return a < b
    }

    var bidiOther = null
    function getBidiPartAt(order, pos) {
        var found
        bidiOther = null
        for (var i = 0; i < order.length; ++i) {
            var cur = order[i]
            if (cur.from < pos && cur.to > pos) { return i }
            if ((cur.from == pos || cur.to == pos)) {
                if (found == null) {
                    found = i
                } else if (compareBidiLevel(order, cur.level, order[found].level)) {
                    if (cur.from != cur.to) { bidiOther = found }
                    return i
                } else {
                    if (cur.from != cur.to) { bidiOther = i }
                    return found
                }
            }
        }
        return found
    }

    function moveInLine(line, pos, dir, byUnit) {
        if (!byUnit) { return pos + dir }
        do { pos += dir }
        while (pos > 0 && isExtendingChar(line.text.charAt(pos)))
        return pos
    }

// This is needed in order to move 'visually' through bi-directional
// text -- i.e., pressing left should make the cursor go left, even
// when in RTL text. The tricky part is the 'jumps', where RTL and
// LTR text touch each other. This often requires the cursor offset
// to move more than one unit, in order to visually move one unit.
    function moveVisually(line, start, dir, byUnit) {
        var bidi = getOrder(line)
        if (!bidi) { return moveLogically(line, start, dir, byUnit) }
        var pos = getBidiPartAt(bidi, start), part = bidi[pos]
        var target = moveInLine(line, start, part.level % 2 ? -dir : dir, byUnit)

        for (;;) {
            if (target > part.from && target < part.to) { return target }
            if (target == part.from || target == part.to) {
                if (getBidiPartAt(bidi, target) == pos) { return target }
                part = bidi[pos += dir]
                return (dir > 0) == part.level % 2 ? part.to : part.from
            } else {
                part = bidi[pos += dir]
                if (!part) { return null }
                if ((dir > 0) == part.level % 2)
                { target = moveInLine(line, part.to, -1, byUnit) }
                else
                { target = moveInLine(line, part.from, 1, byUnit) }
            }
        }
    }

    function moveLogically(line, start, dir, byUnit) {
        var target = start + dir
        if (byUnit) { while (target > 0 && isExtendingChar(line.text.charAt(target))) { target += dir } }
        return target < 0 || target > line.text.length ? null : target
    }

// Bidirectional ordering algorithm
// See http://unicode.org/reports/tr9/tr9-13.html for the algorithm
// that this (partially) implements.

// One-char codes used for character types:
// L (L):   Left-to-Right
// R (R):   Right-to-Left
// r (AL):  Right-to-Left Arabic
// 1 (EN):  European Number
// + (ES):  European Number Separator
// % (ET):  European Number Terminator
// n (AN):  Arabic Number
// , (CS):  Common Number Separator
// m (NSM): Non-Spacing Mark
// b (BN):  Boundary Neutral
// s (B):   Paragraph Separator
// t (S):   Segment Separator
// w (WS):  Whitespace
// N (ON):  Other Neutrals

// Returns null if characters are ordered as they appear
// (left-to-right), or an array of sections ({from, to, level}
// objects) in the order in which they occur visually.
    var bidiOrdering = (function() {
        // Character types for codepoints 0 to 0xff
        var lowTypes = "bbbbbbbbbtstwsbbbbbbbbbbbbbbssstwNN%%%NNNNNN,N,N1111111111NNNNNNNLLLLLLLLLLLLLLLLLLLLLLLLLLNNNNNNLLLLLLLLLLLLLLLLLLLLLLLLLLNNNNbbbbbbsbbbbbbbbbbbbbbbbbbbbbbbbbb,N%%%%NNNNLNNNNN%%11NLNNN1LNNNNNLLLLLLLLLLLLLLLLLLLLLLLNLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLN"
        // Character types for codepoints 0x600 to 0x6ff
        var arabicTypes = "rrrrrrrrrrrr,rNNmmmmmmrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrmmmmmmmmmmmmmmrrrrrrrnnnnnnnnnn%nnrrrmrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrmmmmmmmmmmmmmmmmmmmNmmmm"
        function charType(code) {
            if (code <= 0xf7) { return lowTypes.charAt(code) }
            else if (0x590 <= code && code <= 0x5f4) { return "R" }
            else if (0x600 <= code && code <= 0x6ed) { return arabicTypes.charAt(code - 0x600) }
            else if (0x6ee <= code && code <= 0x8ac) { return "r" }
            else if (0x2000 <= code && code <= 0x200b) { return "w" }
            else if (code == 0x200c) { return "b" }
            else { return "L" }
        }

        var bidiRE = /[\u0590-\u05f4\u0600-\u06ff\u0700-\u08ac]/
        var isNeutral = /[stwN]/, isStrong = /[LRr]/, countsAsLeft = /[Lb1n]/, countsAsNum = /[1n]/
        // Browsers seem to always treat the boundaries of block elements as being L.
        var outerType = "L"

        function BidiSpan(level, from, to) {
            this.level = level
            this.from = from; this.to = to
        }

        return function(str) {
            if (!bidiRE.test(str)) { return false }
            var len = str.length, types = []
            for (var i = 0; i < len; ++i)
            { types.push(charType(str.charCodeAt(i))) }

            // W1. Examine each non-spacing mark (NSM) in the level run, and
            // change the type of the NSM to the type of the previous
            // character. If the NSM is at the start of the level run, it will
            // get the type of sor.
            for (var i$1 = 0, prev = outerType; i$1 < len; ++i$1) {
                var type = types[i$1]
                if (type == "m") { types[i$1] = prev }
                else { prev = type }
            }

            // W2. Search backwards from each instance of a European number
            // until the first strong type (R, L, AL, or sor) is found. If an
            // AL is found, change the type of the European number to Arabic
            // number.
            // W3. Change all ALs to R.
            for (var i$2 = 0, cur = outerType; i$2 < len; ++i$2) {
                var type$1 = types[i$2]
                if (type$1 == "1" && cur == "r") { types[i$2] = "n" }
                else if (isStrong.test(type$1)) { cur = type$1; if (type$1 == "r") { types[i$2] = "R" } }
            }

            // W4. A single European separator between two European numbers
            // changes to a European number. A single common separator between
            // two numbers of the same type changes to that type.
            for (var i$3 = 1, prev$1 = types[0]; i$3 < len - 1; ++i$3) {
                var type$2 = types[i$3]
                if (type$2 == "+" && prev$1 == "1" && types[i$3+1] == "1") { types[i$3] = "1" }
                else if (type$2 == "," && prev$1 == types[i$3+1] &&
                    (prev$1 == "1" || prev$1 == "n")) { types[i$3] = prev$1 }
                prev$1 = type$2
            }

            // W5. A sequence of European terminators adjacent to European
            // numbers changes to all European numbers.
            // W6. Otherwise, separators and terminators change to Other
            // Neutral.
            for (var i$4 = 0; i$4 < len; ++i$4) {
                var type$3 = types[i$4]
                if (type$3 == ",") { types[i$4] = "N" }
                else if (type$3 == "%") {
                    var end = void 0
                    for (end = i$4 + 1; end < len && types[end] == "%"; ++end) {}
                    var replace = (i$4 && types[i$4-1] == "!") || (end < len && types[end] == "1") ? "1" : "N"
                    for (var j = i$4; j < end; ++j) { types[j] = replace }
                    i$4 = end - 1
                }
            }

            // W7. Search backwards from each instance of a European number
            // until the first strong type (R, L, or sor) is found. If an L is
            // found, then change the type of the European number to L.
            for (var i$5 = 0, cur$1 = outerType; i$5 < len; ++i$5) {
                var type$4 = types[i$5]
                if (cur$1 == "L" && type$4 == "1") { types[i$5] = "L" }
                else if (isStrong.test(type$4)) { cur$1 = type$4 }
            }

            // N1. A sequence of neutrals takes the direction of the
            // surrounding strong text if the text on both sides has the same
            // direction. European and Arabic numbers act as if they were R in
            // terms of their influence on neutrals. Start-of-level-run (sor)
            // and end-of-level-run (eor) are used at level run boundaries.
            // N2. Any remaining neutrals take the embedding direction.
            for (var i$6 = 0; i$6 < len; ++i$6) {
                if (isNeutral.test(types[i$6])) {
                    var end$1 = void 0
                    for (end$1 = i$6 + 1; end$1 < len && isNeutral.test(types[end$1]); ++end$1) {}
                    var before = (i$6 ? types[i$6-1] : outerType) == "L"
                    var after = (end$1 < len ? types[end$1] : outerType) == "L"
                    var replace$1 = before || after ? "L" : "R"
                    for (var j$1 = i$6; j$1 < end$1; ++j$1) { types[j$1] = replace$1 }
                    i$6 = end$1 - 1
                }
            }

            // Here we depart from the documented algorithm, in order to avoid
            // building up an actual levels array. Since there are only three
            // levels (0, 1, 2) in an implementation that doesn't take
            // explicit embedding into account, we can build up the order on
            // the fly, without following the level-based algorithm.
            var order = [], m
            for (var i$7 = 0; i$7 < len;) {
                if (countsAsLeft.test(types[i$7])) {
                    var start = i$7
                    for (++i$7; i$7 < len && countsAsLeft.test(types[i$7]); ++i$7) {}
                    order.push(new BidiSpan(0, start, i$7))
                } else {
                    var pos = i$7, at = order.length
                    for (++i$7; i$7 < len && types[i$7] != "L"; ++i$7) {}
                    for (var j$2 = pos; j$2 < i$7;) {
                        if (countsAsNum.test(types[j$2])) {
                            if (pos < j$2) { order.splice(at, 0, new BidiSpan(1, pos, j$2)) }
                            var nstart = j$2
                            for (++j$2; j$2 < i$7 && countsAsNum.test(types[j$2]); ++j$2) {}
                            order.splice(at, 0, new BidiSpan(2, nstart, j$2))
                            pos = j$2
                        } else { ++j$2 }
                    }
                    if (pos < i$7) { order.splice(at, 0, new BidiSpan(1, pos, i$7)) }
                }
            }
            if (order[0].level == 1 && (m = str.match(/^\s+/))) {
                order[0].from = m[0].length
                order.unshift(new BidiSpan(0, 0, m[0].length))
            }
            if (lst(order).level == 1 && (m = str.match(/\s+$/))) {
                lst(order).to -= m[0].length
                order.push(new BidiSpan(0, len - m[0].length, len))
            }
            if (order[0].level == 2)
            { order.unshift(new BidiSpan(1, order[0].to, order[0].to)) }
            if (order[0].level != lst(order).level)
            { order.push(new BidiSpan(order[0].level, len, len)) }

            return order
        }
    })()

// Get the bidi ordering for the given line (and cache it). Returns
// false for lines that are fully left-to-right, and an array of
// BidiSpan objects otherwise.
    function getOrder(line) {
        var order = line.order
        if (order == null) { order = line.order = bidiOrdering(line.text) }
        return order
    }

// EVENT HANDLING

// Lightweight event framework. on/off also work on DOM nodes,
// registering native DOM handlers.

    var on = function(emitter, type, f) {
        if (emitter.addEventListener)
        { emitter.addEventListener(type, f, false) }
        else if (emitter.attachEvent)
        { emitter.attachEvent("on" + type, f) }
        else {
            var map$$1 = emitter._handlers || (emitter._handlers = {})
            var arr = map$$1[type] || (map$$1[type] = [])
            arr.push(f)
        }
    }

    var noHandlers = []
    function getHandlers(emitter, type, copy) {
        var arr = emitter._handlers && emitter._handlers[type]
        if (copy) { return arr && arr.length > 0 ? arr.slice() : noHandlers }
        else { return arr || noHandlers }
    }

    function off(emitter, type, f) {
        if (emitter.removeEventListener)
        { emitter.removeEventListener(type, f, false) }
        else if (emitter.detachEvent)
        { emitter.detachEvent("on" + type, f) }
        else {
            var handlers = getHandlers(emitter, type, false)
            for (var i = 0; i < handlers.length; ++i)
            { if (handlers[i] == f) { handlers.splice(i, 1); break } }
        }
    }

    function signal(emitter, type /*, values...*/) {
        var handlers = getHandlers(emitter, type, true)
        if (!handlers.length) { return }
        var args = Array.prototype.slice.call(arguments, 2)
        for (var i = 0; i < handlers.length; ++i) { handlers[i].apply(null, args) }
    }

// The DOM events that CodeMirror handles can be overridden by
// registering a (non-DOM) handler on the editor for the event name,
// and preventDefault-ing the event in that handler.
    function signalDOMEvent(cm, e, override) {
        if (typeof e == "string")
        { e = {type: e, preventDefault: function() { this.defaultPrevented = true }} }
        signal(cm, override || e.type, cm, e)
        return e_defaultPrevented(e) || e.codemirrorIgnore
    }

    function signalCursorActivity(cm) {
        var arr = cm._handlers && cm._handlers.cursorActivity
        if (!arr) { return }
        var set = cm.curOp.cursorActivityHandlers || (cm.curOp.cursorActivityHandlers = [])
        for (var i = 0; i < arr.length; ++i) { if (indexOf(set, arr[i]) == -1)
        { set.push(arr[i]) } }
    }

    function hasHandler(emitter, type) {
        return getHandlers(emitter, type).length > 0
    }

// Add on and off methods to a constructor's prototype, to make
// registering events on such objects more convenient.
    function eventMixin(ctor) {
        ctor.prototype.on = function(type, f) {on(this, type, f)}
        ctor.prototype.off = function(type, f) {off(this, type, f)}
    }

// Due to the fact that we still support jurassic IE versions, some
// compatibility wrappers are needed.

    function e_preventDefault(e) {
        if (e.preventDefault) { e.preventDefault() }
        else { e.returnValue = false }
    }
    function e_stopPropagation(e) {
        if (e.stopPropagation) { e.stopPropagation() }
        else { e.cancelBubble = true }
    }
    function e_defaultPrevented(e) {
        return e.defaultPrevented != null ? e.defaultPrevented : e.returnValue == false
    }
    function e_stop(e) {e_preventDefault(e); e_stopPropagation(e)}

    function e_target(e) {return e.target || e.srcElement}
    function e_button(e) {
        var b = e.which
        if (b == null) {
            if (e.button & 1) { b = 1 }
            else if (e.button & 2) { b = 3 }
            else if (e.button & 4) { b = 2 }
        }
        if (mac && e.ctrlKey && b == 1) { b = 3 }
        return b
    }

// Detect drag-and-drop
    var dragAndDrop = function() {
        // There is *some* kind of drag-and-drop support in IE6-8, but I
        // couldn't get it to work yet.
        if (ie && ie_version < 9) { return false }
        var div = elt('div')
        return "draggable" in div || "dragDrop" in div
    }()

    var zwspSupported
    function zeroWidthElement(measure) {
        if (zwspSupported == null) {
            var test = elt("span", "\u200b")
            removeChildrenAndAdd(measure, elt("span", [test, document.createTextNode("x")]))
            if (measure.firstChild.offsetHeight != 0)
            { zwspSupported = test.offsetWidth <= 1 && test.offsetHeight > 2 && !(ie && ie_version < 8) }
        }
        var node = zwspSupported ? elt("span", "\u200b") :
            elt("span", "\u00a0", null, "display: inline-block; width: 1px; margin-right: -1px")
        node.setAttribute("cm-text", "")
        return node
    }

// Feature-detect IE's crummy client rect reporting for bidi text
    var badBidiRects
    function hasBadBidiRects(measure) {
        if (badBidiRects != null) { return badBidiRects }
        var txt = removeChildrenAndAdd(measure, document.createTextNode("A\u062eA"))
        var r0 = range(txt, 0, 1).getBoundingClientRect()
        var r1 = range(txt, 1, 2).getBoundingClientRect()
        removeChildren(measure)
        if (!r0 || r0.left == r0.right) { return false } // Safari returns null in some cases (#2780)
        return badBidiRects = (r1.right - r0.right < 3)
    }

// See if "".split is the broken IE version, if so, provide an
// alternative way to split lines.
    var splitLinesAuto = "\n\nb".split(/\n/).length != 3 ? function (string) {
        var pos = 0, result = [], l = string.length
        while (pos <= l) {
            var nl = string.indexOf("\n", pos)
            if (nl == -1) { nl = string.length }
            var line = string.slice(pos, string.charAt(nl - 1) == "\r" ? nl - 1 : nl)
            var rt = line.indexOf("\r")
            if (rt != -1) {
                result.push(line.slice(0, rt))
                pos += rt + 1
            } else {
                result.push(line)
                pos = nl + 1
            }
        }
        return result
    } : function (string) { return string.split(/\r\n?|\n/); }

    var hasSelection = window.getSelection ? function (te) {
        try { return te.selectionStart != te.selectionEnd }
        catch(e) { return false }
    } : function (te) {
        var range$$1
        try {range$$1 = te.ownerDocument.selection.createRange()}
        catch(e) {}
        if (!range$$1 || range$$1.parentElement() != te) { return false }
        return range$$1.compareEndPoints("StartToEnd", range$$1) != 0
    }

    var hasCopyEvent = (function () {
        var e = elt("div")
        if ("oncopy" in e) { return true }
        e.setAttribute("oncopy", "return;")
        return typeof e.oncopy == "function"
    })()

    var badZoomedRects = null
    function hasBadZoomedRects(measure) {
        if (badZoomedRects != null) { return badZoomedRects }
        var node = removeChildrenAndAdd(measure, elt("span", "x"))
        var normal = node.getBoundingClientRect()
        var fromRange = range(node, 0, 1).getBoundingClientRect()
        return badZoomedRects = Math.abs(normal.left - fromRange.left) > 1
    }

// Known modes, by name and by MIME
    var modes = {};
    var mimeModes = {}

// Extra arguments are stored as the mode's dependencies, which is
// used by (legacy) mechanisms like loadmode.js to automatically
// load a mode. (Preferred mechanism is the require/define calls.)
    function defineMode(name, mode) {
        if (arguments.length > 2)
        { mode.dependencies = Array.prototype.slice.call(arguments, 2) }
        modes[name] = mode
    }

    function defineMIME(mime, spec) {
        mimeModes[mime] = spec
    }

// Given a MIME type, a {name, ...options} config object, or a name
// string, return a mode config object.
    function resolveMode(spec) {
        if (typeof spec == "string" && mimeModes.hasOwnProperty(spec)) {
            spec = mimeModes[spec]
        } else if (spec && typeof spec.name == "string" && mimeModes.hasOwnProperty(spec.name)) {
            var found = mimeModes[spec.name]
            if (typeof found == "string") { found = {name: found} }
            spec = createObj(found, spec)
            spec.name = found.name
        } else if (typeof spec == "string" && /^[\w\-]+\/[\w\-]+\+xml$/.test(spec)) {
            return resolveMode("application/xml")
        } else if (typeof spec == "string" && /^[\w\-]+\/[\w\-]+\+json$/.test(spec)) {
            return resolveMode("application/json")
        }
        if (typeof spec == "string") { return {name: spec} }
        else { return spec || {name: "null"} }
    }

// Given a mode spec (anything that resolveMode accepts), find and
// initialize an actual mode object.
    function getMode(options, spec) {
        spec = resolveMode(spec)
        var mfactory = modes[spec.name]
        if (!mfactory) { return getMode(options, "text/plain") }
        var modeObj = mfactory(options, spec)
        if (modeExtensions.hasOwnProperty(spec.name)) {
            var exts = modeExtensions[spec.name]
            for (var prop in exts) {
                if (!exts.hasOwnProperty(prop)) { continue }
                if (modeObj.hasOwnProperty(prop)) { modeObj["_" + prop] = modeObj[prop] }
                modeObj[prop] = exts[prop]
            }
        }
        modeObj.name = spec.name
        if (spec.helperType) { modeObj.helperType = spec.helperType }
        if (spec.modeProps) { for (var prop$1 in spec.modeProps)
        { modeObj[prop$1] = spec.modeProps[prop$1] } }

        return modeObj
    }

// This can be used to attach properties to mode objects from
// outside the actual mode definition.
    var modeExtensions = {}
    function extendMode(mode, properties) {
        var exts = modeExtensions.hasOwnProperty(mode) ? modeExtensions[mode] : (modeExtensions[mode] = {})
        copyObj(properties, exts)
    }

    function copyState(mode, state) {
        if (state === true) { return state }
        if (mode.copyState) { return mode.copyState(state) }
        var nstate = {}
        for (var n in state) {
            var val = state[n]
            if (val instanceof Array) { val = val.concat([]) }
            nstate[n] = val
        }
        return nstate
    }

// Given a mode and a state (for that mode), find the inner mode and
// state at the position that the state refers to.
    function innerMode(mode, state) {
        var info
        while (mode.innerMode) {
            info = mode.innerMode(state)
            if (!info || info.mode == mode) { break }
            state = info.state
            mode = info.mode
        }
        return info || {mode: mode, state: state}
    }

    function startState(mode, a1, a2) {
        return mode.startState ? mode.startState(a1, a2) : true
    }

// STRING STREAM

// Fed to the mode parsers, provides helper functions to make
// parsers more succinct.

    var StringStream = function(string, tabSize) {
        this.pos = this.start = 0
        this.string = string
        this.tabSize = tabSize || 8
        this.lastColumnPos = this.lastColumnValue = 0
        this.lineStart = 0
    }

    StringStream.prototype = {
        eol: function() {return this.pos >= this.string.length},
        sol: function() {return this.pos == this.lineStart},
        peek: function() {return this.string.charAt(this.pos) || undefined},
        next: function() {
            if (this.pos < this.string.length)
            { return this.string.charAt(this.pos++) }
        },
        eat: function(match) {
            var ch = this.string.charAt(this.pos)
            var ok
            if (typeof match == "string") { ok = ch == match }
            else { ok = ch && (match.test ? match.test(ch) : match(ch)) }
            if (ok) {++this.pos; return ch}
        },
        eatWhile: function(match) {
            var start = this.pos
            while (this.eat(match)){}
            return this.pos > start
        },
        eatSpace: function() {
            var this$1 = this;

            var start = this.pos
            while (/[\s\u00a0]/.test(this.string.charAt(this.pos))) { ++this$1.pos }
            return this.pos > start
        },
        skipToEnd: function() {this.pos = this.string.length},
        skipTo: function(ch) {
            var found = this.string.indexOf(ch, this.pos)
            if (found > -1) {this.pos = found; return true}
        },
        backUp: function(n) {this.pos -= n},
        column: function() {
            if (this.lastColumnPos < this.start) {
                this.lastColumnValue = countColumn(this.string, this.start, this.tabSize, this.lastColumnPos, this.lastColumnValue)
                this.lastColumnPos = this.start
            }
            return this.lastColumnValue - (this.lineStart ? countColumn(this.string, this.lineStart, this.tabSize) : 0)
        },
        indentation: function() {
            return countColumn(this.string, null, this.tabSize) -
                (this.lineStart ? countColumn(this.string, this.lineStart, this.tabSize) : 0)
        },
        match: function(pattern, consume, caseInsensitive) {
            if (typeof pattern == "string") {
                var cased = function (str) { return caseInsensitive ? str.toLowerCase() : str; }
                var substr = this.string.substr(this.pos, pattern.length)
                if (cased(substr) == cased(pattern)) {
                    if (consume !== false) { this.pos += pattern.length }
                    return true
                }
            } else {
                var match = this.string.slice(this.pos).match(pattern)
                if (match && match.index > 0) { return null }
                if (match && consume !== false) { this.pos += match[0].length }
                return match
            }
        },
        current: function(){return this.string.slice(this.start, this.pos)},
        hideFirstChars: function(n, inner) {
            this.lineStart += n
            try { return inner() }
            finally { this.lineStart -= n }
        }
    }

// Compute a style array (an array starting with a mode generation
// -- for invalidation -- followed by pairs of end positions and
// style strings), which is used to highlight the tokens on the
// line.
    function highlightLine(cm, line, state, forceToEnd) {
        // A styles array always starts with a number identifying the
        // mode/overlays that it is based on (for easy invalidation).
        var st = [cm.state.modeGen], lineClasses = {}
        // Compute the base array of styles
        runMode(cm, line.text, cm.doc.mode, state, function (end, style) { return st.push(end, style); },
            lineClasses, forceToEnd)

        // Run overlays, adjust style array.
        var loop = function ( o ) {
            var overlay = cm.state.overlays[o], i = 1, at = 0
            runMode(cm, line.text, overlay.mode, true, function (end, style) {
                var start = i
                // Ensure there's a token end at the current position, and that i points at it
                while (at < end) {
                    var i_end = st[i]
                    if (i_end > end)
                    { st.splice(i, 1, end, st[i+1], i_end) }
                    i += 2
                    at = Math.min(end, i_end)
                }
                if (!style) { return }
                if (overlay.opaque) {
                    st.splice(start, i - start, end, "overlay " + style)
                    i = start + 2
                } else {
                    for (; start < i; start += 2) {
                        var cur = st[start+1]
                        st[start+1] = (cur ? cur + " " : "") + "overlay " + style
                    }
                }
            }, lineClasses)
        };

        for (var o = 0; o < cm.state.overlays.length; ++o) loop( o );

        return {styles: st, classes: lineClasses.bgClass || lineClasses.textClass ? lineClasses : null}
    }

    function getLineStyles(cm, line, updateFrontier) {
        if (!line.styles || line.styles[0] != cm.state.modeGen) {
            var state = getStateBefore(cm, lineNo(line))
            var result = highlightLine(cm, line, line.text.length > cm.options.maxHighlightLength ? copyState(cm.doc.mode, state) : state)
            line.stateAfter = state
            line.styles = result.styles
            if (result.classes) { line.styleClasses = result.classes }
            else if (line.styleClasses) { line.styleClasses = null }
            if (updateFrontier === cm.doc.frontier) { cm.doc.frontier++ }
        }
        return line.styles
    }

    function getStateBefore(cm, n, precise) {
        var doc = cm.doc, display = cm.display
        if (!doc.mode.startState) { return true }
        var pos = findStartLine(cm, n, precise), state = pos > doc.first && getLine(doc, pos-1).stateAfter
        if (!state) { state = startState(doc.mode) }
        else { state = copyState(doc.mode, state) }
        doc.iter(pos, n, function (line) {
            processLine(cm, line.text, state)
            var save = pos == n - 1 || pos % 5 == 0 || pos >= display.viewFrom && pos < display.viewTo
            line.stateAfter = save ? copyState(doc.mode, state) : null
            ++pos
        })
        if (precise) { doc.frontier = pos }
        return state
    }

// Lightweight form of highlight -- proceed over this line and
// update state, but don't save a style array. Used for lines that
// aren't currently visible.
    function processLine(cm, text, state, startAt) {
        var mode = cm.doc.mode
        var stream = new StringStream(text, cm.options.tabSize)
        stream.start = stream.pos = startAt || 0
        if (text == "") { callBlankLine(mode, state) }
        while (!stream.eol()) {
            readToken(mode, stream, state)
            stream.start = stream.pos
        }
    }

    function callBlankLine(mode, state) {
        if (mode.blankLine) { return mode.blankLine(state) }
        if (!mode.innerMode) { return }
        var inner = innerMode(mode, state)
        if (inner.mode.blankLine) { return inner.mode.blankLine(inner.state) }
    }

    function readToken(mode, stream, state, inner) {
        for (var i = 0; i < 10; i++) {
            if (inner) { inner[0] = innerMode(mode, state).mode }
            var style = mode.token(stream, state)
            if (stream.pos > stream.start) { return style }
        }
        throw new Error("Mode " + mode.name + " failed to advance stream.")
    }

// Utility for getTokenAt and getLineTokens
    function takeToken(cm, pos, precise, asArray) {
        var getObj = function (copy) { return ({
            start: stream.start, end: stream.pos,
            string: stream.current(),
            type: style || null,
            state: copy ? copyState(doc.mode, state) : state
        }); }

        var doc = cm.doc, mode = doc.mode, style
        pos = clipPos(doc, pos)
        var line = getLine(doc, pos.line), state = getStateBefore(cm, pos.line, precise)
        var stream = new StringStream(line.text, cm.options.tabSize), tokens
        if (asArray) { tokens = [] }
        while ((asArray || stream.pos < pos.ch) && !stream.eol()) {
            stream.start = stream.pos
            style = readToken(mode, stream, state)
            if (asArray) { tokens.push(getObj(true)) }
        }
        return asArray ? tokens : getObj()
    }

    function extractLineClasses(type, output) {
        if (type) { for (;;) {
            var lineClass = type.match(/(?:^|\s+)line-(background-)?(\S+)/)
            if (!lineClass) { break }
            type = type.slice(0, lineClass.index) + type.slice(lineClass.index + lineClass[0].length)
            var prop = lineClass[1] ? "bgClass" : "textClass"
            if (output[prop] == null)
            { output[prop] = lineClass[2] }
            else if (!(new RegExp("(?:^|\s)" + lineClass[2] + "(?:$|\s)")).test(output[prop]))
            { output[prop] += " " + lineClass[2] }
        } }
        return type
    }

// Run the given mode's parser over a line, calling f for each token.
    function runMode(cm, text, mode, state, f, lineClasses, forceToEnd) {
        var flattenSpans = mode.flattenSpans
        if (flattenSpans == null) { flattenSpans = cm.options.flattenSpans }
        var curStart = 0, curStyle = null
        var stream = new StringStream(text, cm.options.tabSize), style
        var inner = cm.options.addModeClass && [null]
        if (text == "") { extractLineClasses(callBlankLine(mode, state), lineClasses) }
        while (!stream.eol()) {
            if (stream.pos > cm.options.maxHighlightLength) {
                flattenSpans = false
                if (forceToEnd) { processLine(cm, text, state, stream.pos) }
                stream.pos = text.length
                style = null
            } else {
                style = extractLineClasses(readToken(mode, stream, state, inner), lineClasses)
            }
            if (inner) {
                var mName = inner[0].name
                if (mName) { style = "m-" + (style ? mName + " " + style : mName) }
            }
            if (!flattenSpans || curStyle != style) {
                while (curStart < stream.start) {
                    curStart = Math.min(stream.start, curStart + 5000)
                    f(curStart, curStyle)
                }
                curStyle = style
            }
            stream.start = stream.pos
        }
        while (curStart < stream.pos) {
            // Webkit seems to refuse to render text nodes longer than 57444
            // characters, and returns inaccurate measurements in nodes
            // starting around 5000 chars.
            var pos = Math.min(stream.pos, curStart + 5000)
            f(pos, curStyle)
            curStart = pos
        }
    }

// Finds the line to start with when starting a parse. Tries to
// find a line with a stateAfter, so that it can start with a
// valid state. If that fails, it returns the line with the
// smallest indentation, which tends to need the least context to
// parse correctly.
    function findStartLine(cm, n, precise) {
        var minindent, minline, doc = cm.doc
        var lim = precise ? -1 : n - (cm.doc.mode.innerMode ? 1000 : 100)
        for (var search = n; search > lim; --search) {
            if (search <= doc.first) { return doc.first }
            var line = getLine(doc, search - 1)
            if (line.stateAfter && (!precise || search <= doc.frontier)) { return search }
            var indented = countColumn(line.text, null, cm.options.tabSize)
            if (minline == null || minindent > indented) {
                minline = search - 1
                minindent = indented
            }
        }
        return minline
    }

// LINE DATA STRUCTURE

// Line objects. These hold state related to a line, including
// highlighting info (the styles array).
    function Line(text, markedSpans, estimateHeight) {
        this.text = text
        attachMarkedSpans(this, markedSpans)
        this.height = estimateHeight ? estimateHeight(this) : 1
    }
    eventMixin(Line)
    Line.prototype.lineNo = function() { return lineNo(this) }

// Change the content (text, markers) of a line. Automatically
// invalidates cached information and tries to re-estimate the
// line's height.
    function updateLine(line, text, markedSpans, estimateHeight) {
        line.text = text
        if (line.stateAfter) { line.stateAfter = null }
        if (line.styles) { line.styles = null }
        if (line.order != null) { line.order = null }
        detachMarkedSpans(line)
        attachMarkedSpans(line, markedSpans)
        var estHeight = estimateHeight ? estimateHeight(line) : 1
        if (estHeight != line.height) { updateLineHeight(line, estHeight) }
    }

// Detach a line from the document tree and its markers.
    function cleanUpLine(line) {
        line.parent = null
        detachMarkedSpans(line)
    }

// Convert a style as returned by a mode (either null, or a string
// containing one or more styles) to a CSS style. This is cached,
// and also looks for line-wide styles.
    var styleToClassCache = {};
    var styleToClassCacheWithMode = {}
    function interpretTokenStyle(style, options) {
        if (!style || /^\s*$/.test(style)) { return null }
        var cache = options.addModeClass ? styleToClassCacheWithMode : styleToClassCache
        return cache[style] ||
            (cache[style] = style.replace(/\S+/g, "cm-$&"))
    }

// Render the DOM representation of the text of a line. Also builds
// up a 'line map', which points at the DOM nodes that represent
// specific stretches of text, and is used by the measuring code.
// The returned object contains the DOM node, this map, and
// information about line-wide styles that were set by the mode.
    function buildLineContent(cm, lineView) {
        // The padding-right forces the element to have a 'border', which
        // is needed on Webkit to be able to get line-level bounding
        // rectangles for it (in measureChar).
        var content = elt("span", null, null, webkit ? "padding-right: .1px" : null)
        var builder = {pre: elt("pre", [content], "CodeMirror-line"), content: content,
            col: 0, pos: 0, cm: cm,
            trailingSpace: false,
            splitSpaces: (ie || webkit) && cm.getOption("lineWrapping")}
        lineView.measure = {}

        // Iterate over the logical lines that make up this visual line.
        for (var i = 0; i <= (lineView.rest ? lineView.rest.length : 0); i++) {
            var line = i ? lineView.rest[i - 1] : lineView.line, order = void 0
            builder.pos = 0
            builder.addToken = buildToken
            // Optionally wire in some hacks into the token-rendering
            // algorithm, to deal with browser quirks.
            if (hasBadBidiRects(cm.display.measure) && (order = getOrder(line)))
            { builder.addToken = buildTokenBadBidi(builder.addToken, order) }
            builder.map = []
            var allowFrontierUpdate = lineView != cm.display.externalMeasured && lineNo(line)
            insertLineContent(line, builder, getLineStyles(cm, line, allowFrontierUpdate))
            if (line.styleClasses) {
                if (line.styleClasses.bgClass)
                { builder.bgClass = joinClasses(line.styleClasses.bgClass, builder.bgClass || "") }
                if (line.styleClasses.textClass)
                { builder.textClass = joinClasses(line.styleClasses.textClass, builder.textClass || "") }
            }

            // Ensure at least a single node is present, for measuring.
            if (builder.map.length == 0)
            { builder.map.push(0, 0, builder.content.appendChild(zeroWidthElement(cm.display.measure))) }

            // Store the map and a cache object for the current logical line
            if (i == 0) {
                lineView.measure.map = builder.map
                lineView.measure.cache = {}
            } else {
                (lineView.measure.maps || (lineView.measure.maps = [])).push(builder.map)
                ;(lineView.measure.caches || (lineView.measure.caches = [])).push({})
            }
        }

        // See issue #2901
        if (webkit) {
            var last = builder.content.lastChild
            if (/\bcm-tab\b/.test(last.className) || (last.querySelector && last.querySelector(".cm-tab")))
            { builder.content.className = "cm-tab-wrap-hack" }
        }

        signal(cm, "renderLine", cm, lineView.line, builder.pre)
        if (builder.pre.className)
        { builder.textClass = joinClasses(builder.pre.className, builder.textClass || "") }

        return builder
    }

    function defaultSpecialCharPlaceholder(ch) {
        var token = elt("span", "\u2022", "cm-invalidchar")
        token.title = "\\u" + ch.charCodeAt(0).toString(16)
        token.setAttribute("aria-label", token.title)
        return token
    }

// Build up the DOM representation for a single token, and add it to
// the line map. Takes care to render special characters separately.
    function buildToken(builder, text, style, startStyle, endStyle, title, css) {
        if (!text) { return }
        var displayText = builder.splitSpaces ? splitSpaces(text, builder.trailingSpace) : text
        var special = builder.cm.state.specialChars, mustWrap = false
        var content
        if (!special.test(text)) {
            builder.col += text.length
            content = document.createTextNode(displayText)
            builder.map.push(builder.pos, builder.pos + text.length, content)
            if (ie && ie_version < 9) { mustWrap = true }
            builder.pos += text.length
        } else {
            content = document.createDocumentFragment()
            var pos = 0
            while (true) {
                special.lastIndex = pos
                var m = special.exec(text)
                var skipped = m ? m.index - pos : text.length - pos
                if (skipped) {
                    var txt = document.createTextNode(displayText.slice(pos, pos + skipped))
                    if (ie && ie_version < 9) { content.appendChild(elt("span", [txt])) }
                    else { content.appendChild(txt) }
                    builder.map.push(builder.pos, builder.pos + skipped, txt)
                    builder.col += skipped
                    builder.pos += skipped
                }
                if (!m) { break }
                pos += skipped + 1
                var txt$1 = void 0
                if (m[0] == "\t") {
                    var tabSize = builder.cm.options.tabSize, tabWidth = tabSize - builder.col % tabSize
                    txt$1 = content.appendChild(elt("span", spaceStr(tabWidth), "cm-tab"))
                    txt$1.setAttribute("role", "presentation")
                    txt$1.setAttribute("cm-text", "\t")
                    builder.col += tabWidth
                } else if (m[0] == "\r" || m[0] == "\n") {
                    txt$1 = content.appendChild(elt("span", m[0] == "\r" ? "\u240d" : "\u2424", "cm-invalidchar"))
                    txt$1.setAttribute("cm-text", m[0])
                    builder.col += 1
                } else {
                    txt$1 = builder.cm.options.specialCharPlaceholder(m[0])
                    txt$1.setAttribute("cm-text", m[0])
                    if (ie && ie_version < 9) { content.appendChild(elt("span", [txt$1])) }
                    else { content.appendChild(txt$1) }
                    builder.col += 1
                }
                builder.map.push(builder.pos, builder.pos + 1, txt$1)
                builder.pos++
            }
        }
        builder.trailingSpace = displayText.charCodeAt(text.length - 1) == 32
        if (style || startStyle || endStyle || mustWrap || css) {
            var fullStyle = style || ""
            if (startStyle) { fullStyle += startStyle }
            if (endStyle) { fullStyle += endStyle }
            var token = elt("span", [content], fullStyle, css)
            if (title) { token.title = title }
            return builder.content.appendChild(token)
        }
        builder.content.appendChild(content)
    }

    function splitSpaces(text, trailingBefore) {
        if (text.length > 1 && !/  /.test(text)) { return text }
        var spaceBefore = trailingBefore, result = ""
        for (var i = 0; i < text.length; i++) {
            var ch = text.charAt(i)
            if (ch == " " && spaceBefore && (i == text.length - 1 || text.charCodeAt(i + 1) == 32))
            { ch = "\u00a0" }
            result += ch
            spaceBefore = ch == " "
        }
        return result
    }

// Work around nonsense dimensions being reported for stretches of
// right-to-left text.
    function buildTokenBadBidi(inner, order) {
        return function (builder, text, style, startStyle, endStyle, title, css) {
            style = style ? style + " cm-force-border" : "cm-force-border"
            var start = builder.pos, end = start + text.length
            for (;;) {
                // Find the part that overlaps with the start of this text
                var part = void 0
                for (var i = 0; i < order.length; i++) {
                    part = order[i]
                    if (part.to > start && part.from <= start) { break }
                }
                if (part.to >= end) { return inner(builder, text, style, startStyle, endStyle, title, css) }
                inner(builder, text.slice(0, part.to - start), style, startStyle, null, title, css)
                startStyle = null
                text = text.slice(part.to - start)
                start = part.to
            }
        }
    }

    function buildCollapsedSpan(builder, size, marker, ignoreWidget) {
        var widget = !ignoreWidget && marker.widgetNode
        if (widget) { builder.map.push(builder.pos, builder.pos + size, widget) }
        if (!ignoreWidget && builder.cm.display.input.needsContentAttribute) {
            if (!widget)
            { widget = builder.content.appendChild(document.createElement("span")) }
            widget.setAttribute("cm-marker", marker.id)
        }
        if (widget) {
            builder.cm.display.input.setUneditable(widget)
            builder.content.appendChild(widget)
        }
        builder.pos += size
        builder.trailingSpace = false
    }

// Outputs a number of spans to make up a line, taking highlighting
// and marked text into account.
    function insertLineContent(line, builder, styles) {
        var spans = line.markedSpans, allText = line.text, at = 0
        if (!spans) {
            for (var i$1 = 1; i$1 < styles.length; i$1+=2)
            { builder.addToken(builder, allText.slice(at, at = styles[i$1]), interpretTokenStyle(styles[i$1+1], builder.cm.options)) }
            return
        }

        var len = allText.length, pos = 0, i = 1, text = "", style, css
        var nextChange = 0, spanStyle, spanEndStyle, spanStartStyle, title, collapsed
        for (;;) {
            if (nextChange == pos) { // Update current marker set
                spanStyle = spanEndStyle = spanStartStyle = title = css = ""
                collapsed = null; nextChange = Infinity
                var foundBookmarks = [], endStyles = void 0
                for (var j = 0; j < spans.length; ++j) {
                    var sp = spans[j], m = sp.marker
                    if (m.type == "bookmark" && sp.from == pos && m.widgetNode) {
                        foundBookmarks.push(m)
                    } else if (sp.from <= pos && (sp.to == null || sp.to > pos || m.collapsed && sp.to == pos && sp.from == pos)) {
                        if (sp.to != null && sp.to != pos && nextChange > sp.to) {
                            nextChange = sp.to
                            spanEndStyle = ""
                        }
                        if (m.className) { spanStyle += " " + m.className }
                        if (m.css) { css = (css ? css + ";" : "") + m.css }
                        if (m.startStyle && sp.from == pos) { spanStartStyle += " " + m.startStyle }
                        if (m.endStyle && sp.to == nextChange) { (endStyles || (endStyles = [])).push(m.endStyle, sp.to) }
                        if (m.title && !title) { title = m.title }
                        if (m.collapsed && (!collapsed || compareCollapsedMarkers(collapsed.marker, m) < 0))
                        { collapsed = sp }
                    } else if (sp.from > pos && nextChange > sp.from) {
                        nextChange = sp.from
                    }
                }
                if (endStyles) { for (var j$1 = 0; j$1 < endStyles.length; j$1 += 2)
                { if (endStyles[j$1 + 1] == nextChange) { spanEndStyle += " " + endStyles[j$1] } } }

                if (!collapsed || collapsed.from == pos) { for (var j$2 = 0; j$2 < foundBookmarks.length; ++j$2)
                { buildCollapsedSpan(builder, 0, foundBookmarks[j$2]) } }
                if (collapsed && (collapsed.from || 0) == pos) {
                    buildCollapsedSpan(builder, (collapsed.to == null ? len + 1 : collapsed.to) - pos,
                        collapsed.marker, collapsed.from == null)
                    if (collapsed.to == null) { return }
                    if (collapsed.to == pos) { collapsed = false }
                }
            }
            if (pos >= len) { break }

            var upto = Math.min(len, nextChange)
            while (true) {
                if (text) {
                    var end = pos + text.length
                    if (!collapsed) {
                        var tokenText = end > upto ? text.slice(0, upto - pos) : text
                        builder.addToken(builder, tokenText, style ? style + spanStyle : spanStyle,
                            spanStartStyle, pos + tokenText.length == nextChange ? spanEndStyle : "", title, css)
                    }
                    if (end >= upto) {text = text.slice(upto - pos); pos = upto; break}
                    pos = end
                    spanStartStyle = ""
                }
                text = allText.slice(at, at = styles[i++])
                style = interpretTokenStyle(styles[i++], builder.cm.options)
            }
        }
    }


// These objects are used to represent the visible (currently drawn)
// part of the document. A LineView may correspond to multiple
// logical lines, if those are connected by collapsed ranges.
    function LineView(doc, line, lineN) {
        // The starting line
        this.line = line
        // Continuing lines, if any
        this.rest = visualLineContinued(line)
        // Number of logical lines in this visual line
        this.size = this.rest ? lineNo(lst(this.rest)) - lineN + 1 : 1
        this.node = this.text = null
        this.hidden = lineIsHidden(doc, line)
    }

// Create a range of LineView objects for the given lines.
    function buildViewArray(cm, from, to) {
        var array = [], nextPos
        for (var pos = from; pos < to; pos = nextPos) {
            var view = new LineView(cm.doc, getLine(cm.doc, pos), pos)
            nextPos = pos + view.size
            array.push(view)
        }
        return array
    }

    var operationGroup = null

    function pushOperation(op) {
        if (operationGroup) {
            operationGroup.ops.push(op)
        } else {
            op.ownsGroup = operationGroup = {
                ops: [op],
                delayedCallbacks: []
            }
        }
    }

    function fireCallbacksForOps(group) {
        // Calls delayed callbacks and cursorActivity handlers until no
        // new ones appear
        var callbacks = group.delayedCallbacks, i = 0
        do {
            for (; i < callbacks.length; i++)
            { callbacks[i].call(null) }
            for (var j = 0; j < group.ops.length; j++) {
                var op = group.ops[j]
                if (op.cursorActivityHandlers)
                { while (op.cursorActivityCalled < op.cursorActivityHandlers.length)
                { op.cursorActivityHandlers[op.cursorActivityCalled++].call(null, op.cm) } }
            }
        } while (i < callbacks.length)
    }

    function finishOperation(op, endCb) {
        var group = op.ownsGroup
        if (!group) { return }

        try { fireCallbacksForOps(group) }
        finally {
            operationGroup = null
            endCb(group)
        }
    }

    var orphanDelayedCallbacks = null

// Often, we want to signal events at a point where we are in the
// middle of some work, but don't want the handler to start calling
// other methods on the editor, which might be in an inconsistent
// state or simply not expect any other events to happen.
// signalLater looks whether there are any handlers, and schedules
// them to be executed when the last operation ends, or, if no
// operation is active, when a timeout fires.
    function signalLater(emitter, type /*, values...*/) {
        var arr = getHandlers(emitter, type, false)
        if (!arr.length) { return }
        var args = Array.prototype.slice.call(arguments, 2), list
        if (operationGroup) {
            list = operationGroup.delayedCallbacks
        } else if (orphanDelayedCallbacks) {
            list = orphanDelayedCallbacks
        } else {
            list = orphanDelayedCallbacks = []
            setTimeout(fireOrphanDelayed, 0)
        }
        var loop = function ( i ) {
            list.push(function () { return arr[i].apply(null, args); })
        };

        for (var i = 0; i < arr.length; ++i)
            loop( i );
    }

    function fireOrphanDelayed() {
        var delayed = orphanDelayedCallbacks
        orphanDelayedCallbacks = null
        for (var i = 0; i < delayed.length; ++i) { delayed[i]() }
    }

// When an aspect of a line changes, a string is added to
// lineView.changes. This updates the relevant part of the line's
// DOM structure.
    function updateLineForChanges(cm, lineView, lineN, dims) {
        for (var j = 0; j < lineView.changes.length; j++) {
            var type = lineView.changes[j]
            if (type == "text") { updateLineText(cm, lineView) }
            else if (type == "gutter") { updateLineGutter(cm, lineView, lineN, dims) }
            else if (type == "class") { updateLineClasses(lineView) }
            else if (type == "widget") { updateLineWidgets(cm, lineView, dims) }
        }
        lineView.changes = null
    }

// Lines with gutter elements, widgets or a background class need to
// be wrapped, and have the extra elements added to the wrapper div
    function ensureLineWrapped(lineView) {
        if (lineView.node == lineView.text) {
            lineView.node = elt("div", null, null, "position: relative")
            if (lineView.text.parentNode)
            { lineView.text.parentNode.replaceChild(lineView.node, lineView.text) }
            lineView.node.appendChild(lineView.text)
            if (ie && ie_version < 8) { lineView.node.style.zIndex = 2 }
        }
        return lineView.node
    }

    function updateLineBackground(lineView) {
        var cls = lineView.bgClass ? lineView.bgClass + " " + (lineView.line.bgClass || "") : lineView.line.bgClass
        if (cls) { cls += " CodeMirror-linebackground" }
        if (lineView.background) {
            if (cls) { lineView.background.className = cls }
            else { lineView.background.parentNode.removeChild(lineView.background); lineView.background = null }
        } else if (cls) {
            var wrap = ensureLineWrapped(lineView)
            lineView.background = wrap.insertBefore(elt("div", null, cls), wrap.firstChild)
        }
    }

// Wrapper around buildLineContent which will reuse the structure
// in display.externalMeasured when possible.
    function getLineContent(cm, lineView) {
        var ext = cm.display.externalMeasured
        if (ext && ext.line == lineView.line) {
            cm.display.externalMeasured = null
            lineView.measure = ext.measure
            return ext.built
        }
        return buildLineContent(cm, lineView)
    }

// Redraw the line's text. Interacts with the background and text
// classes because the mode may output tokens that influence these
// classes.
    function updateLineText(cm, lineView) {
        var cls = lineView.text.className
        var built = getLineContent(cm, lineView)
        if (lineView.text == lineView.node) { lineView.node = built.pre }
        lineView.text.parentNode.replaceChild(built.pre, lineView.text)
        lineView.text = built.pre
        if (built.bgClass != lineView.bgClass || built.textClass != lineView.textClass) {
            lineView.bgClass = built.bgClass
            lineView.textClass = built.textClass
            updateLineClasses(lineView)
        } else if (cls) {
            lineView.text.className = cls
        }
    }

    function updateLineClasses(lineView) {
        updateLineBackground(lineView)
        if (lineView.line.wrapClass)
        { ensureLineWrapped(lineView).className = lineView.line.wrapClass }
        else if (lineView.node != lineView.text)
        { lineView.node.className = "" }
        var textClass = lineView.textClass ? lineView.textClass + " " + (lineView.line.textClass || "") : lineView.line.textClass
        lineView.text.className = textClass || ""
    }

    function updateLineGutter(cm, lineView, lineN, dims) {
        if (lineView.gutter) {
            lineView.node.removeChild(lineView.gutter)
            lineView.gutter = null
        }
        if (lineView.gutterBackground) {
            lineView.node.removeChild(lineView.gutterBackground)
            lineView.gutterBackground = null
        }
        if (lineView.line.gutterClass) {
            var wrap = ensureLineWrapped(lineView)
            lineView.gutterBackground = elt("div", null, "CodeMirror-gutter-background " + lineView.line.gutterClass,
                ("left: " + (cm.options.fixedGutter ? dims.fixedPos : -dims.gutterTotalWidth) + "px; width: " + (dims.gutterTotalWidth) + "px"))
            wrap.insertBefore(lineView.gutterBackground, lineView.text)
        }
        var markers = lineView.line.gutterMarkers
        if (cm.options.lineNumbers || markers) {
            var wrap$1 = ensureLineWrapped(lineView)
            var gutterWrap = lineView.gutter = elt("div", null, "CodeMirror-gutter-wrapper", ("left: " + (cm.options.fixedGutter ? dims.fixedPos : -dims.gutterTotalWidth) + "px"))
            cm.display.input.setUneditable(gutterWrap)
            wrap$1.insertBefore(gutterWrap, lineView.text)
            if (lineView.line.gutterClass)
            { gutterWrap.className += " " + lineView.line.gutterClass }
            if (cm.options.lineNumbers && (!markers || !markers["CodeMirror-linenumbers"]))
            { lineView.lineNumber = gutterWrap.appendChild(
                elt("div", lineNumberFor(cm.options, lineN),
                    "CodeMirror-linenumber CodeMirror-gutter-elt",
                    ("left: " + (dims.gutterLeft["CodeMirror-linenumbers"]) + "px; width: " + (cm.display.lineNumInnerWidth) + "px"))) }
            if (markers) { for (var k = 0; k < cm.options.gutters.length; ++k) {
                var id = cm.options.gutters[k], found = markers.hasOwnProperty(id) && markers[id]
                if (found)
                { gutterWrap.appendChild(elt("div", [found], "CodeMirror-gutter-elt",
                    ("left: " + (dims.gutterLeft[id]) + "px; width: " + (dims.gutterWidth[id]) + "px"))) }
            } }
        }
    }

    function updateLineWidgets(cm, lineView, dims) {
        if (lineView.alignable) { lineView.alignable = null }
        for (var node = lineView.node.firstChild, next = void 0; node; node = next) {
            next = node.nextSibling
            if (node.className == "CodeMirror-linewidget")
            { lineView.node.removeChild(node) }
        }
        insertLineWidgets(cm, lineView, dims)
    }

// Build a line's DOM representation from scratch
    function buildLineElement(cm, lineView, lineN, dims) {
        var built = getLineContent(cm, lineView)
        lineView.text = lineView.node = built.pre
        if (built.bgClass) { lineView.bgClass = built.bgClass }
        if (built.textClass) { lineView.textClass = built.textClass }

        updateLineClasses(lineView)
        updateLineGutter(cm, lineView, lineN, dims)
        insertLineWidgets(cm, lineView, dims)
        return lineView.node
    }

// A lineView may contain multiple logical lines (when merged by
// collapsed spans). The widgets for all of them need to be drawn.
    function insertLineWidgets(cm, lineView, dims) {
        insertLineWidgetsFor(cm, lineView.line, lineView, dims, true)
        if (lineView.rest) { for (var i = 0; i < lineView.rest.length; i++)
        { insertLineWidgetsFor(cm, lineView.rest[i], lineView, dims, false) } }
    }

    function insertLineWidgetsFor(cm, line, lineView, dims, allowAbove) {
        if (!line.widgets) { return }
        var wrap = ensureLineWrapped(lineView)
        for (var i = 0, ws = line.widgets; i < ws.length; ++i) {
            var widget = ws[i], node = elt("div", [widget.node], "CodeMirror-linewidget")
            if (!widget.handleMouseEvents) { node.setAttribute("cm-ignore-events", "true") }
            positionLineWidget(widget, node, lineView, dims)
            cm.display.input.setUneditable(node)
            if (allowAbove && widget.above)
            { wrap.insertBefore(node, lineView.gutter || lineView.text) }
            else
            { wrap.appendChild(node) }
            signalLater(widget, "redraw")
        }
    }

    function positionLineWidget(widget, node, lineView, dims) {
        if (widget.noHScroll) {
            (lineView.alignable || (lineView.alignable = [])).push(node)
            var width = dims.wrapperWidth
            node.style.left = dims.fixedPos + "px"
            if (!widget.coverGutter) {
                width -= dims.gutterTotalWidth
                node.style.paddingLeft = dims.gutterTotalWidth + "px"
            }
            node.style.width = width + "px"
        }
        if (widget.coverGutter) {
            node.style.zIndex = 5
            node.style.position = "relative"
            if (!widget.noHScroll) { node.style.marginLeft = -dims.gutterTotalWidth + "px" }
        }
    }

    function widgetHeight(widget) {
        if (widget.height != null) { return widget.height }
        var cm = widget.doc.cm
        if (!cm) { return 0 }
        if (!contains(document.body, widget.node)) {
            var parentStyle = "position: relative;"
            if (widget.coverGutter)
            { parentStyle += "margin-left: -" + cm.display.gutters.offsetWidth + "px;" }
            if (widget.noHScroll)
            { parentStyle += "width: " + cm.display.wrapper.clientWidth + "px;" }
            removeChildrenAndAdd(cm.display.measure, elt("div", [widget.node], null, parentStyle))
        }
        return widget.height = widget.node.parentNode.offsetHeight
    }

// Return true when the given mouse event happened in a widget
    function eventInWidget(display, e) {
        for (var n = e_target(e); n != display.wrapper; n = n.parentNode) {
            if (!n || (n.nodeType == 1 && n.getAttribute("cm-ignore-events") == "true") ||
                (n.parentNode == display.sizer && n != display.mover))
            { return true }
        }
    }

// POSITION MEASUREMENT

    function paddingTop(display) {return display.lineSpace.offsetTop}
    function paddingVert(display) {return display.mover.offsetHeight - display.lineSpace.offsetHeight}
    function paddingH(display) {
        if (display.cachedPaddingH) { return display.cachedPaddingH }
        var e = removeChildrenAndAdd(display.measure, elt("pre", "x"))
        var style = window.getComputedStyle ? window.getComputedStyle(e) : e.currentStyle
        var data = {left: parseInt(style.paddingLeft), right: parseInt(style.paddingRight)}
        if (!isNaN(data.left) && !isNaN(data.right)) { display.cachedPaddingH = data }
        return data
    }

    function scrollGap(cm) { return scrollerGap - cm.display.nativeBarWidth }
    function displayWidth(cm) {
        return cm.display.scroller.clientWidth - scrollGap(cm) - cm.display.barWidth
    }
    function displayHeight(cm) {
        return cm.display.scroller.clientHeight - scrollGap(cm) - cm.display.barHeight
    }

// Ensure the lineView.wrapping.heights array is populated. This is
// an array of bottom offsets for the lines that make up a drawn
// line. When lineWrapping is on, there might be more than one
// height.
    function ensureLineHeights(cm, lineView, rect) {
        var wrapping = cm.options.lineWrapping
        var curWidth = wrapping && displayWidth(cm)
        if (!lineView.measure.heights || wrapping && lineView.measure.width != curWidth) {
            var heights = lineView.measure.heights = []
            if (wrapping) {
                lineView.measure.width = curWidth
                var rects = lineView.text.firstChild.getClientRects()
                for (var i = 0; i < rects.length - 1; i++) {
                    var cur = rects[i], next = rects[i + 1]
                    if (Math.abs(cur.bottom - next.bottom) > 2)
                    { heights.push((cur.bottom + next.top) / 2 - rect.top) }
                }
            }
            heights.push(rect.bottom - rect.top)
        }
    }

// Find a line map (mapping character offsets to text nodes) and a
// measurement cache for the given line number. (A line view might
// contain multiple lines when collapsed ranges are present.)
    function mapFromLineView(lineView, line, lineN) {
        if (lineView.line == line)
        { return {map: lineView.measure.map, cache: lineView.measure.cache} }
        for (var i = 0; i < lineView.rest.length; i++)
        { if (lineView.rest[i] == line)
        { return {map: lineView.measure.maps[i], cache: lineView.measure.caches[i]} } }
        for (var i$1 = 0; i$1 < lineView.rest.length; i$1++)
        { if (lineNo(lineView.rest[i$1]) > lineN)
        { return {map: lineView.measure.maps[i$1], cache: lineView.measure.caches[i$1], before: true} } }
    }

// Render a line into the hidden node display.externalMeasured. Used
// when measurement is needed for a line that's not in the viewport.
    function updateExternalMeasurement(cm, line) {
        line = visualLine(line)
        var lineN = lineNo(line)
        var view = cm.display.externalMeasured = new LineView(cm.doc, line, lineN)
        view.lineN = lineN
        var built = view.built = buildLineContent(cm, view)
        view.text = built.pre
        removeChildrenAndAdd(cm.display.lineMeasure, built.pre)
        return view
    }

// Get a {top, bottom, left, right} box (in line-local coordinates)
// for a given character.
    function measureChar(cm, line, ch, bias) {
        return measureCharPrepared(cm, prepareMeasureForLine(cm, line), ch, bias)
    }

// Find a line view that corresponds to the given line number.
    function findViewForLine(cm, lineN) {
        if (lineN >= cm.display.viewFrom && lineN < cm.display.viewTo)
        { return cm.display.view[findViewIndex(cm, lineN)] }
        var ext = cm.display.externalMeasured
        if (ext && lineN >= ext.lineN && lineN < ext.lineN + ext.size)
        { return ext }
    }

// Measurement can be split in two steps, the set-up work that
// applies to the whole line, and the measurement of the actual
// character. Functions like coordsChar, that need to do a lot of
// measurements in a row, can thus ensure that the set-up work is
// only done once.
    function prepareMeasureForLine(cm, line) {
        var lineN = lineNo(line)
        var view = findViewForLine(cm, lineN)
        if (view && !view.text) {
            view = null
        } else if (view && view.changes) {
            updateLineForChanges(cm, view, lineN, getDimensions(cm))
            cm.curOp.forceUpdate = true
        }
        if (!view)
        { view = updateExternalMeasurement(cm, line) }

        var info = mapFromLineView(view, line, lineN)
        return {
            line: line, view: view, rect: null,
            map: info.map, cache: info.cache, before: info.before,
            hasHeights: false
        }
    }

// Given a prepared measurement object, measures the position of an
// actual character (or fetches it from the cache).
    function measureCharPrepared(cm, prepared, ch, bias, varHeight) {
        if (prepared.before) { ch = -1 }
        var key = ch + (bias || ""), found
        if (prepared.cache.hasOwnProperty(key)) {
            found = prepared.cache[key]
        } else {
            if (!prepared.rect)
            { prepared.rect = prepared.view.text.getBoundingClientRect() }
            if (!prepared.hasHeights) {
                ensureLineHeights(cm, prepared.view, prepared.rect)
                prepared.hasHeights = true
            }
            found = measureCharInner(cm, prepared, ch, bias)
            if (!found.bogus) { prepared.cache[key] = found }
        }
        return {left: found.left, right: found.right,
            top: varHeight ? found.rtop : found.top,
            bottom: varHeight ? found.rbottom : found.bottom}
    }

    var nullRect = {left: 0, right: 0, top: 0, bottom: 0}

    function nodeAndOffsetInLineMap(map$$1, ch, bias) {
        var node, start, end, collapse, mStart, mEnd
        // First, search the line map for the text node corresponding to,
        // or closest to, the target character.
        for (var i = 0; i < map$$1.length; i += 3) {
            mStart = map$$1[i]
            mEnd = map$$1[i + 1]
            if (ch < mStart) {
                start = 0; end = 1
                collapse = "left"
            } else if (ch < mEnd) {
                start = ch - mStart
                end = start + 1
            } else if (i == map$$1.length - 3 || ch == mEnd && map$$1[i + 3] > ch) {
                end = mEnd - mStart
                start = end - 1
                if (ch >= mEnd) { collapse = "right" }
            }
            if (start != null) {
                node = map$$1[i + 2]
                if (mStart == mEnd && bias == (node.insertLeft ? "left" : "right"))
                { collapse = bias }
                if (bias == "left" && start == 0)
                { while (i && map$$1[i - 2] == map$$1[i - 3] && map$$1[i - 1].insertLeft) {
                    node = map$$1[(i -= 3) + 2]
                    collapse = "left"
                } }
                if (bias == "right" && start == mEnd - mStart)
                { while (i < map$$1.length - 3 && map$$1[i + 3] == map$$1[i + 4] && !map$$1[i + 5].insertLeft) {
                    node = map$$1[(i += 3) + 2]
                    collapse = "right"
                } }
                break
            }
        }
        return {node: node, start: start, end: end, collapse: collapse, coverStart: mStart, coverEnd: mEnd}
    }

    function getUsefulRect(rects, bias) {
        var rect = nullRect
        if (bias == "left") { for (var i = 0; i < rects.length; i++) {
            if ((rect = rects[i]).left != rect.right) { break }
        } } else { for (var i$1 = rects.length - 1; i$1 >= 0; i$1--) {
            if ((rect = rects[i$1]).left != rect.right) { break }
        } }
        return rect
    }

    function measureCharInner(cm, prepared, ch, bias) {
        var place = nodeAndOffsetInLineMap(prepared.map, ch, bias)
        var node = place.node, start = place.start, end = place.end, collapse = place.collapse

        var rect
        if (node.nodeType == 3) { // If it is a text node, use a range to retrieve the coordinates.
            for (var i$1 = 0; i$1 < 4; i$1++) { // Retry a maximum of 4 times when nonsense rectangles are returned
                while (start && isExtendingChar(prepared.line.text.charAt(place.coverStart + start))) { --start }
                while (place.coverStart + end < place.coverEnd && isExtendingChar(prepared.line.text.charAt(place.coverStart + end))) { ++end }
                if (ie && ie_version < 9 && start == 0 && end == place.coverEnd - place.coverStart)
                { rect = node.parentNode.getBoundingClientRect() }
                else
                { rect = getUsefulRect(range(node, start, end).getClientRects(), bias) }
                if (rect.left || rect.right || start == 0) { break }
                end = start
                start = start - 1
                collapse = "right"
            }
            if (ie && ie_version < 11) { rect = maybeUpdateRectForZooming(cm.display.measure, rect) }
        } else { // If it is a widget, simply get the box for the whole widget.
            if (start > 0) { collapse = bias = "right" }
            var rects
            if (cm.options.lineWrapping && (rects = node.getClientRects()).length > 1)
            { rect = rects[bias == "right" ? rects.length - 1 : 0] }
            else
            { rect = node.getBoundingClientRect() }
        }
        if (ie && ie_version < 9 && !start && (!rect || !rect.left && !rect.right)) {
            var rSpan = node.parentNode.getClientRects()[0]
            if (rSpan)
            { rect = {left: rSpan.left, right: rSpan.left + charWidth(cm.display), top: rSpan.top, bottom: rSpan.bottom} }
            else
            { rect = nullRect }
        }

        var rtop = rect.top - prepared.rect.top, rbot = rect.bottom - prepared.rect.top
        var mid = (rtop + rbot) / 2
        var heights = prepared.view.measure.heights
        var i = 0
        for (; i < heights.length - 1; i++)
        { if (mid < heights[i]) { break } }
        var top = i ? heights[i - 1] : 0, bot = heights[i]
        var result = {left: (collapse == "right" ? rect.right : rect.left) - prepared.rect.left,
            right: (collapse == "left" ? rect.left : rect.right) - prepared.rect.left,
            top: top, bottom: bot}
        if (!rect.left && !rect.right) { result.bogus = true }
        if (!cm.options.singleCursorHeightPerLine) { result.rtop = rtop; result.rbottom = rbot }

        return result
    }

// Work around problem with bounding client rects on ranges being
// returned incorrectly when zoomed on IE10 and below.
    function maybeUpdateRectForZooming(measure, rect) {
        if (!window.screen || screen.logicalXDPI == null ||
            screen.logicalXDPI == screen.deviceXDPI || !hasBadZoomedRects(measure))
        { return rect }
        var scaleX = screen.logicalXDPI / screen.deviceXDPI
        var scaleY = screen.logicalYDPI / screen.deviceYDPI
        return {left: rect.left * scaleX, right: rect.right * scaleX,
            top: rect.top * scaleY, bottom: rect.bottom * scaleY}
    }

    function clearLineMeasurementCacheFor(lineView) {
        if (lineView.measure) {
            lineView.measure.cache = {}
            lineView.measure.heights = null
            if (lineView.rest) { for (var i = 0; i < lineView.rest.length; i++)
            { lineView.measure.caches[i] = {} } }
        }
    }

    function clearLineMeasurementCache(cm) {
        cm.display.externalMeasure = null
        removeChildren(cm.display.lineMeasure)
        for (var i = 0; i < cm.display.view.length; i++)
        { clearLineMeasurementCacheFor(cm.display.view[i]) }
    }

    function clearCaches(cm) {
        clearLineMeasurementCache(cm)
        cm.display.cachedCharWidth = cm.display.cachedTextHeight = cm.display.cachedPaddingH = null
        if (!cm.options.lineWrapping) { cm.display.maxLineChanged = true }
        cm.display.lineNumChars = null
    }

    function pageScrollX() { return window.pageXOffset || (document.documentElement || document.body).scrollLeft }
    function pageScrollY() { return window.pageYOffset || (document.documentElement || document.body).scrollTop }

// Converts a {top, bottom, left, right} box from line-local
// coordinates into another coordinate system. Context may be one of
// "line", "div" (display.lineDiv), "local"./null (editor), "window",
// or "page".
    function intoCoordSystem(cm, lineObj, rect, context) {
        if (lineObj.widgets) { for (var i = 0; i < lineObj.widgets.length; ++i) { if (lineObj.widgets[i].above) {
            var size = widgetHeight(lineObj.widgets[i])
            rect.top += size; rect.bottom += size
        } } }
        if (context == "line") { return rect }
        if (!context) { context = "local" }
        var yOff = heightAtLine(lineObj)
        if (context == "local") { yOff += paddingTop(cm.display) }
        else { yOff -= cm.display.viewOffset }
        if (context == "page" || context == "window") {
            var lOff = cm.display.lineSpace.getBoundingClientRect()
            yOff += lOff.top + (context == "window" ? 0 : pageScrollY())
            var xOff = lOff.left + (context == "window" ? 0 : pageScrollX())
            rect.left += xOff; rect.right += xOff
        }
        rect.top += yOff; rect.bottom += yOff
        return rect
    }

// Coverts a box from "div" coords to another coordinate system.
// Context may be "window", "page", "div", or "local"./null.
    function fromCoordSystem(cm, coords, context) {
        if (context == "div") { return coords }
        var left = coords.left, top = coords.top
        // First move into "page" coordinate system
        if (context == "page") {
            left -= pageScrollX()
            top -= pageScrollY()
        } else if (context == "local" || !context) {
            var localBox = cm.display.sizer.getBoundingClientRect()
            left += localBox.left
            top += localBox.top
        }

        var lineSpaceBox = cm.display.lineSpace.getBoundingClientRect()
        return {left: left - lineSpaceBox.left, top: top - lineSpaceBox.top}
    }

    function charCoords(cm, pos, context, lineObj, bias) {
        if (!lineObj) { lineObj = getLine(cm.doc, pos.line) }
        return intoCoordSystem(cm, lineObj, measureChar(cm, lineObj, pos.ch, bias), context)
    }

// Returns a box for a given cursor position, which may have an
// 'other' property containing the position of the secondary cursor
// on a bidi boundary.
    function cursorCoords(cm, pos, context, lineObj, preparedMeasure, varHeight) {
        lineObj = lineObj || getLine(cm.doc, pos.line)
        if (!preparedMeasure) { preparedMeasure = prepareMeasureForLine(cm, lineObj) }
        function get(ch, right) {
            var m = measureCharPrepared(cm, preparedMeasure, ch, right ? "right" : "left", varHeight)
            if (right) { m.left = m.right; } else { m.right = m.left }
            return intoCoordSystem(cm, lineObj, m, context)
        }
        function getBidi(ch, partPos) {
            var part = order[partPos], right = part.level % 2
            if (ch == bidiLeft(part) && partPos && part.level < order[partPos - 1].level) {
                part = order[--partPos]
                ch = bidiRight(part) - (part.level % 2 ? 0 : 1)
                right = true
            } else if (ch == bidiRight(part) && partPos < order.length - 1 && part.level < order[partPos + 1].level) {
                part = order[++partPos]
                ch = bidiLeft(part) - part.level % 2
                right = false
            }
            if (right && ch == part.to && ch > part.from) { return get(ch - 1) }
            return get(ch, right)
        }
        var order = getOrder(lineObj), ch = pos.ch
        if (!order) { return get(ch) }
        var partPos = getBidiPartAt(order, ch)
        var val = getBidi(ch, partPos)
        if (bidiOther != null) { val.other = getBidi(ch, bidiOther) }
        return val
    }

// Used to cheaply estimate the coordinates for a position. Used for
// intermediate scroll updates.
    function estimateCoords(cm, pos) {
        var left = 0
        pos = clipPos(cm.doc, pos)
        if (!cm.options.lineWrapping) { left = charWidth(cm.display) * pos.ch }
        var lineObj = getLine(cm.doc, pos.line)
        var top = heightAtLine(lineObj) + paddingTop(cm.display)
        return {left: left, right: left, top: top, bottom: top + lineObj.height}
    }

// Positions returned by coordsChar contain some extra information.
// xRel is the relative x position of the input coordinates compared
// to the found position (so xRel > 0 means the coordinates are to
// the right of the character position, for example). When outside
// is true, that means the coordinates lie outside the line's
// vertical range.
    function PosWithInfo(line, ch, outside, xRel) {
        var pos = Pos(line, ch)
        pos.xRel = xRel
        if (outside) { pos.outside = true }
        return pos
    }

// Compute the character position closest to the given coordinates.
// Input must be lineSpace-local ("div" coordinate system).
    function coordsChar(cm, x, y) {
        var doc = cm.doc
        y += cm.display.viewOffset
        if (y < 0) { return PosWithInfo(doc.first, 0, true, -1) }
        var lineN = lineAtHeight(doc, y), last = doc.first + doc.size - 1
        if (lineN > last)
        { return PosWithInfo(doc.first + doc.size - 1, getLine(doc, last).text.length, true, 1) }
        if (x < 0) { x = 0 }

        var lineObj = getLine(doc, lineN)
        for (;;) {
            var found = coordsCharInner(cm, lineObj, lineN, x, y)
            var merged = collapsedSpanAtEnd(lineObj)
            var mergedPos = merged && merged.find(0, true)
            if (merged && (found.ch > mergedPos.from.ch || found.ch == mergedPos.from.ch && found.xRel > 0))
            { lineN = lineNo(lineObj = mergedPos.to.line) }
            else
            { return found }
        }
    }

    function coordsCharInner(cm, lineObj, lineNo$$1, x, y) {
        var innerOff = y - heightAtLine(lineObj)
        var wrongLine = false, adjust = 2 * cm.display.wrapper.clientWidth
        var preparedMeasure = prepareMeasureForLine(cm, lineObj)

        function getX(ch) {
            var sp = cursorCoords(cm, Pos(lineNo$$1, ch), "line", lineObj, preparedMeasure)
            wrongLine = true
            if (innerOff > sp.bottom) { return sp.left - adjust }
            else if (innerOff < sp.top) { return sp.left + adjust }
            else { wrongLine = false }
            return sp.left
        }

        var bidi = getOrder(lineObj), dist = lineObj.text.length
        var from = lineLeft(lineObj), to = lineRight(lineObj)
        var fromX = getX(from), fromOutside = wrongLine, toX = getX(to), toOutside = wrongLine

        if (x > toX) { return PosWithInfo(lineNo$$1, to, toOutside, 1) }
        // Do a binary search between these bounds.
        for (;;) {
            if (bidi ? to == from || to == moveVisually(lineObj, from, 1) : to - from <= 1) {
                var ch = x < fromX || x - fromX <= toX - x ? from : to
                var outside = ch == from ? fromOutside : toOutside
                var xDiff = x - (ch == from ? fromX : toX)
                // This is a kludge to handle the case where the coordinates
                // are after a line-wrapped line. We should replace it with a
                // more general handling of cursor positions around line
                // breaks. (Issue #4078)
                if (toOutside && !bidi && !/\s/.test(lineObj.text.charAt(ch)) && xDiff > 0 &&
                    ch < lineObj.text.length && preparedMeasure.view.measure.heights.length > 1) {
                    var charSize = measureCharPrepared(cm, preparedMeasure, ch, "right")
                    if (innerOff <= charSize.bottom && innerOff >= charSize.top && Math.abs(x - charSize.right) < xDiff) {
                        outside = false
                        ch++
                        xDiff = x - charSize.right
                    }
                }
                while (isExtendingChar(lineObj.text.charAt(ch))) { ++ch }
                var pos = PosWithInfo(lineNo$$1, ch, outside, xDiff < -1 ? -1 : xDiff > 1 ? 1 : 0)
                return pos
            }
            var step = Math.ceil(dist / 2), middle = from + step
            if (bidi) {
                middle = from
                for (var i = 0; i < step; ++i) { middle = moveVisually(lineObj, middle, 1) }
            }
            var middleX = getX(middle)
            if (middleX > x) {to = middle; toX = middleX; if (toOutside = wrongLine) { toX += 1000; } dist = step}
            else {from = middle; fromX = middleX; fromOutside = wrongLine; dist -= step}
        }
    }

    var measureText
// Compute the default text height.
    function textHeight(display) {
        if (display.cachedTextHeight != null) { return display.cachedTextHeight }
        if (measureText == null) {
            measureText = elt("pre")
            // Measure a bunch of lines, for browsers that compute
            // fractional heights.
            for (var i = 0; i < 49; ++i) {
                measureText.appendChild(document.createTextNode("x"))
                measureText.appendChild(elt("br"))
            }
            measureText.appendChild(document.createTextNode("x"))
        }
        removeChildrenAndAdd(display.measure, measureText)
        var height = measureText.offsetHeight / 50
        if (height > 3) { display.cachedTextHeight = height }
        removeChildren(display.measure)
        return height || 1
    }

// Compute the default character width.
    function charWidth(display) {
        if (display.cachedCharWidth != null) { return display.cachedCharWidth }
        var anchor = elt("span", "xxxxxxxxxx")
        var pre = elt("pre", [anchor])
        removeChildrenAndAdd(display.measure, pre)
        var rect = anchor.getBoundingClientRect(), width = (rect.right - rect.left) / 10
        if (width > 2) { display.cachedCharWidth = width }
        return width || 10
    }

// Do a bulk-read of the DOM positions and sizes needed to draw the
// view, so that we don't interleave reading and writing to the DOM.
    function getDimensions(cm) {
        var d = cm.display, left = {}, width = {}
        var gutterLeft = d.gutters.clientLeft
        for (var n = d.gutters.firstChild, i = 0; n; n = n.nextSibling, ++i) {
            left[cm.options.gutters[i]] = n.offsetLeft + n.clientLeft + gutterLeft
            width[cm.options.gutters[i]] = n.clientWidth
        }
        return {fixedPos: compensateForHScroll(d),
            gutterTotalWidth: d.gutters.offsetWidth,
            gutterLeft: left,
            gutterWidth: width,
            wrapperWidth: d.wrapper.clientWidth}
    }

// Computes display.scroller.scrollLeft + display.gutters.offsetWidth,
// but using getBoundingClientRect to get a sub-pixel-accurate
// result.
    function compensateForHScroll(display) {
        return display.scroller.getBoundingClientRect().left - display.sizer.getBoundingClientRect().left
    }

// Returns a function that estimates the height of a line, to use as
// first approximation until the line becomes visible (and is thus
// properly measurable).
    function estimateHeight(cm) {
        var th = textHeight(cm.display), wrapping = cm.options.lineWrapping
        var perLine = wrapping && Math.max(5, cm.display.scroller.clientWidth / charWidth(cm.display) - 3)
        return function (line) {
            if (lineIsHidden(cm.doc, line)) { return 0 }

            var widgetsHeight = 0
            if (line.widgets) { for (var i = 0; i < line.widgets.length; i++) {
                if (line.widgets[i].height) { widgetsHeight += line.widgets[i].height }
            } }

            if (wrapping)
            { return widgetsHeight + (Math.ceil(line.text.length / perLine) || 1) * th }
            else
            { return widgetsHeight + th }
        }
    }

    function estimateLineHeights(cm) {
        var doc = cm.doc, est = estimateHeight(cm)
        doc.iter(function (line) {
            var estHeight = est(line)
            if (estHeight != line.height) { updateLineHeight(line, estHeight) }
        })
    }

// Given a mouse event, find the corresponding position. If liberal
// is false, it checks whether a gutter or scrollbar was clicked,
// and returns null if it was. forRect is used by rectangular
// selections, and tries to estimate a character position even for
// coordinates beyond the right of the text.
    function posFromMouse(cm, e, liberal, forRect) {
        var display = cm.display
        if (!liberal && e_target(e).getAttribute("cm-not-content") == "true") { return null }

        var x, y, space = display.lineSpace.getBoundingClientRect()
        // Fails unpredictably on IE[67] when mouse is dragged around quickly.
        try { x = e.clientX - space.left; y = e.clientY - space.top }
        catch (e) { return null }
        var coords = coordsChar(cm, x, y), line
        if (forRect && coords.xRel == 1 && (line = getLine(cm.doc, coords.line).text).length == coords.ch) {
            var colDiff = countColumn(line, line.length, cm.options.tabSize) - line.length
            coords = Pos(coords.line, Math.max(0, Math.round((x - paddingH(cm.display).left) / charWidth(cm.display)) - colDiff))
        }
        return coords
    }

// Find the view element corresponding to a given line. Return null
// when the line isn't visible.
    function findViewIndex(cm, n) {
        if (n >= cm.display.viewTo) { return null }
        n -= cm.display.viewFrom
        if (n < 0) { return null }
        var view = cm.display.view
        for (var i = 0; i < view.length; i++) {
            n -= view[i].size
            if (n < 0) { return i }
        }
    }

    function updateSelection(cm) {
        cm.display.input.showSelection(cm.display.input.prepareSelection())
    }

    function prepareSelection(cm, primary) {
        var doc = cm.doc, result = {}
        var curFragment = result.cursors = document.createDocumentFragment()
        var selFragment = result.selection = document.createDocumentFragment()

        for (var i = 0; i < doc.sel.ranges.length; i++) {
            if (primary === false && i == doc.sel.primIndex) { continue }
            var range$$1 = doc.sel.ranges[i]
            if (range$$1.from().line >= cm.display.viewTo || range$$1.to().line < cm.display.viewFrom) { continue }
            var collapsed = range$$1.empty()
            if (collapsed || cm.options.showCursorWhenSelecting)
            { drawSelectionCursor(cm, range$$1.head, curFragment) }
            if (!collapsed)
            { drawSelectionRange(cm, range$$1, selFragment) }
        }
        return result
    }

// Draws a cursor for the given range
    function drawSelectionCursor(cm, head, output) {
        var pos = cursorCoords(cm, head, "div", null, null, !cm.options.singleCursorHeightPerLine)

        var cursor = output.appendChild(elt("div", "\u00a0", "CodeMirror-cursor"))
        cursor.style.left = pos.left + "px"
        cursor.style.top = pos.top + "px"
        cursor.style.height = Math.max(0, pos.bottom - pos.top) * cm.options.cursorHeight + "px"

        if (pos.other) {
            // Secondary cursor, shown when on a 'jump' in bi-directional text
            var otherCursor = output.appendChild(elt("div", "\u00a0", "CodeMirror-cursor CodeMirror-secondarycursor"))
            otherCursor.style.display = ""
            otherCursor.style.left = pos.other.left + "px"
            otherCursor.style.top = pos.other.top + "px"
            otherCursor.style.height = (pos.other.bottom - pos.other.top) * .85 + "px"
        }
    }

// Draws the given range as a highlighted selection
    function drawSelectionRange(cm, range$$1, output) {
        var display = cm.display, doc = cm.doc
        var fragment = document.createDocumentFragment()
        var padding = paddingH(cm.display), leftSide = padding.left
        var rightSide = Math.max(display.sizerWidth, displayWidth(cm) - display.sizer.offsetLeft) - padding.right

        function add(left, top, width, bottom) {
            if (top < 0) { top = 0 }
            top = Math.round(top)
            bottom = Math.round(bottom)
            fragment.appendChild(elt("div", null, "CodeMirror-selected", ("position: absolute; left: " + left + "px;\n                             top: " + top + "px; width: " + (width == null ? rightSide - left : width) + "px;\n                             height: " + (bottom - top) + "px")))
        }

        function drawForLine(line, fromArg, toArg) {
            var lineObj = getLine(doc, line)
            var lineLen = lineObj.text.length
            var start, end
            function coords(ch, bias) {
                return charCoords(cm, Pos(line, ch), "div", lineObj, bias)
            }

            iterateBidiSections(getOrder(lineObj), fromArg || 0, toArg == null ? lineLen : toArg, function (from, to, dir) {
                var leftPos = coords(from, "left"), rightPos, left, right
                if (from == to) {
                    rightPos = leftPos
                    left = right = leftPos.left
                } else {
                    rightPos = coords(to - 1, "right")
                    if (dir == "rtl") { var tmp = leftPos; leftPos = rightPos; rightPos = tmp }
                    left = leftPos.left
                    right = rightPos.right
                }
                if (fromArg == null && from == 0) { left = leftSide }
                if (rightPos.top - leftPos.top > 3) { // Different lines, draw top part
                    add(left, leftPos.top, null, leftPos.bottom)
                    left = leftSide
                    if (leftPos.bottom < rightPos.top) { add(left, leftPos.bottom, null, rightPos.top) }
                }
                if (toArg == null && to == lineLen) { right = rightSide }
                if (!start || leftPos.top < start.top || leftPos.top == start.top && leftPos.left < start.left)
                { start = leftPos }
                if (!end || rightPos.bottom > end.bottom || rightPos.bottom == end.bottom && rightPos.right > end.right)
                { end = rightPos }
                if (left < leftSide + 1) { left = leftSide }
                add(left, rightPos.top, right - left, rightPos.bottom)
            })
            return {start: start, end: end}
        }

        var sFrom = range$$1.from(), sTo = range$$1.to()
        if (sFrom.line == sTo.line) {
            drawForLine(sFrom.line, sFrom.ch, sTo.ch)
        } else {
            var fromLine = getLine(doc, sFrom.line), toLine = getLine(doc, sTo.line)
            var singleVLine = visualLine(fromLine) == visualLine(toLine)
            var leftEnd = drawForLine(sFrom.line, sFrom.ch, singleVLine ? fromLine.text.length + 1 : null).end
            var rightStart = drawForLine(sTo.line, singleVLine ? 0 : null, sTo.ch).start
            if (singleVLine) {
                if (leftEnd.top < rightStart.top - 2) {
                    add(leftEnd.right, leftEnd.top, null, leftEnd.bottom)
                    add(leftSide, rightStart.top, rightStart.left, rightStart.bottom)
                } else {
                    add(leftEnd.right, leftEnd.top, rightStart.left - leftEnd.right, leftEnd.bottom)
                }
            }
            if (leftEnd.bottom < rightStart.top)
            { add(leftSide, leftEnd.bottom, null, rightStart.top) }
        }

        output.appendChild(fragment)
    }

// Cursor-blinking
    function restartBlink(cm) {
        if (!cm.state.focused) { return }
        var display = cm.display
        clearInterval(display.blinker)
        var on = true
        display.cursorDiv.style.visibility = ""
        if (cm.options.cursorBlinkRate > 0)
        { display.blinker = setInterval(function () { return display.cursorDiv.style.visibility = (on = !on) ? "" : "hidden"; },
            cm.options.cursorBlinkRate) }
        else if (cm.options.cursorBlinkRate < 0)
        { display.cursorDiv.style.visibility = "hidden" }
    }

    function ensureFocus(cm) {
        if (!cm.state.focused) { cm.display.input.focus(); onFocus(cm) }
    }

    function delayBlurEvent(cm) {
        cm.state.delayingBlurEvent = true
        setTimeout(function () { if (cm.state.delayingBlurEvent) {
            cm.state.delayingBlurEvent = false
            onBlur(cm)
        } }, 100)
    }

    function onFocus(cm, e) {
        if (cm.state.delayingBlurEvent) { cm.state.delayingBlurEvent = false }

        if (cm.options.readOnly == "nocursor") { return }
        if (!cm.state.focused) {
            signal(cm, "focus", cm, e)
            cm.state.focused = true
            addClass(cm.display.wrapper, "CodeMirror-focused")
            // This test prevents this from firing when a context
            // menu is closed (since the input reset would kill the
            // select-all detection hack)
            if (!cm.curOp && cm.display.selForContextMenu != cm.doc.sel) {
                cm.display.input.reset()
                if (webkit) { setTimeout(function () { return cm.display.input.reset(true); }, 20) } // Issue #1730
            }
            cm.display.input.receivedFocus()
        }
        restartBlink(cm)
    }
    function onBlur(cm, e) {
        if (cm.state.delayingBlurEvent) { return }

        if (cm.state.focused) {
            signal(cm, "blur", cm, e)
            cm.state.focused = false
            rmClass(cm.display.wrapper, "CodeMirror-focused")
        }
        clearInterval(cm.display.blinker)
        setTimeout(function () { if (!cm.state.focused) { cm.display.shift = false } }, 150)
    }

// Re-align line numbers and gutter marks to compensate for
// horizontal scrolling.
    function alignHorizontally(cm) {
        var display = cm.display, view = display.view
        if (!display.alignWidgets && (!display.gutters.firstChild || !cm.options.fixedGutter)) { return }
        var comp = compensateForHScroll(display) - display.scroller.scrollLeft + cm.doc.scrollLeft
        var gutterW = display.gutters.offsetWidth, left = comp + "px"
        for (var i = 0; i < view.length; i++) { if (!view[i].hidden) {
            if (cm.options.fixedGutter) {
                if (view[i].gutter)
                { view[i].gutter.style.left = left }
                if (view[i].gutterBackground)
                { view[i].gutterBackground.style.left = left }
            }
            var align = view[i].alignable
            if (align) { for (var j = 0; j < align.length; j++)
            { align[j].style.left = left } }
        } }
        if (cm.options.fixedGutter)
        { display.gutters.style.left = (comp + gutterW) + "px" }
    }

// Used to ensure that the line number gutter is still the right
// size for the current document size. Returns true when an update
// is needed.
    function maybeUpdateLineNumberWidth(cm) {
        if (!cm.options.lineNumbers) { return false }
        var doc = cm.doc, last = lineNumberFor(cm.options, doc.first + doc.size - 1), display = cm.display
        if (last.length != display.lineNumChars) {
            var test = display.measure.appendChild(elt("div", [elt("div", last)],
                "CodeMirror-linenumber CodeMirror-gutter-elt"))
            var innerW = test.firstChild.offsetWidth, padding = test.offsetWidth - innerW
            display.lineGutter.style.width = ""
            display.lineNumInnerWidth = Math.max(innerW, display.lineGutter.offsetWidth - padding) + 1
            display.lineNumWidth = display.lineNumInnerWidth + padding
            display.lineNumChars = display.lineNumInnerWidth ? last.length : -1
            display.lineGutter.style.width = display.lineNumWidth + "px"
            updateGutterSpace(cm)
            return true
        }
        return false
    }

// Read the actual heights of the rendered lines, and update their
// stored heights to match.
    function updateHeightsInViewport(cm) {
        var display = cm.display
        var prevBottom = display.lineDiv.offsetTop
        for (var i = 0; i < display.view.length; i++) {
            var cur = display.view[i], height = void 0
            if (cur.hidden) { continue }
            if (ie && ie_version < 8) {
                var bot = cur.node.offsetTop + cur.node.offsetHeight
                height = bot - prevBottom
                prevBottom = bot
            } else {
                var box = cur.node.getBoundingClientRect()
                height = box.bottom - box.top
            }
            var diff = cur.line.height - height
            if (height < 2) { height = textHeight(display) }
            if (diff > .001 || diff < -.001) {
                updateLineHeight(cur.line, height)
                updateWidgetHeight(cur.line)
                if (cur.rest) { for (var j = 0; j < cur.rest.length; j++)
                { updateWidgetHeight(cur.rest[j]) } }
            }
        }
    }

// Read and store the height of line widgets associated with the
// given line.
    function updateWidgetHeight(line) {
        if (line.widgets) { for (var i = 0; i < line.widgets.length; ++i)
        { line.widgets[i].height = line.widgets[i].node.parentNode.offsetHeight } }
    }

// Compute the lines that are visible in a given viewport (defaults
// the the current scroll position). viewport may contain top,
// height, and ensure (see op.scrollToPos) properties.
    function visibleLines(display, doc, viewport) {
        var top = viewport && viewport.top != null ? Math.max(0, viewport.top) : display.scroller.scrollTop
        top = Math.floor(top - paddingTop(display))
        var bottom = viewport && viewport.bottom != null ? viewport.bottom : top + display.wrapper.clientHeight

        var from = lineAtHeight(doc, top), to = lineAtHeight(doc, bottom)
        // Ensure is a {from: {line, ch}, to: {line, ch}} object, and
        // forces those lines into the viewport (if possible).
        if (viewport && viewport.ensure) {
            var ensureFrom = viewport.ensure.from.line, ensureTo = viewport.ensure.to.line
            if (ensureFrom < from) {
                from = ensureFrom
                to = lineAtHeight(doc, heightAtLine(getLine(doc, ensureFrom)) + display.wrapper.clientHeight)
            } else if (Math.min(ensureTo, doc.lastLine()) >= to) {
                from = lineAtHeight(doc, heightAtLine(getLine(doc, ensureTo)) - display.wrapper.clientHeight)
                to = ensureTo
            }
        }
        return {from: from, to: Math.max(to, from + 1)}
    }

// Sync the scrollable area and scrollbars, ensure the viewport
// covers the visible area.
    function setScrollTop(cm, val) {
        if (Math.abs(cm.doc.scrollTop - val) < 2) { return }
        cm.doc.scrollTop = val
        if (!gecko) { updateDisplaySimple(cm, {top: val}) }
        if (cm.display.scroller.scrollTop != val) { cm.display.scroller.scrollTop = val }
        cm.display.scrollbars.setScrollTop(val)
        if (gecko) { updateDisplaySimple(cm) }
        startWorker(cm, 100)
    }
// Sync scroller and scrollbar, ensure the gutter elements are
// aligned.
    function setScrollLeft(cm, val, isScroller) {
        if (isScroller ? val == cm.doc.scrollLeft : Math.abs(cm.doc.scrollLeft - val) < 2) { return }
        val = Math.min(val, cm.display.scroller.scrollWidth - cm.display.scroller.clientWidth)
        cm.doc.scrollLeft = val
        alignHorizontally(cm)
        if (cm.display.scroller.scrollLeft != val) { cm.display.scroller.scrollLeft = val }
        cm.display.scrollbars.setScrollLeft(val)
    }

// Since the delta values reported on mouse wheel events are
// unstandardized between browsers and even browser versions, and
// generally horribly unpredictable, this code starts by measuring
// the scroll effect that the first few mouse wheel events have,
// and, from that, detects the way it can convert deltas to pixel
// offsets afterwards.
//
// The reason we want to know the amount a wheel event will scroll
// is that it gives us a chance to update the display before the
// actual scrolling happens, reducing flickering.

    var wheelSamples = 0;
    var wheelPixelsPerUnit = null
// Fill in a browser-detected starting value on browsers where we
// know one. These don't have to be accurate -- the result of them
// being wrong would just be a slight flicker on the first wheel
// scroll (if it is large enough).
    if (ie) { wheelPixelsPerUnit = -.53 }
    else if (gecko) { wheelPixelsPerUnit = 15 }
    else if (chrome) { wheelPixelsPerUnit = -.7 }
    else if (safari) { wheelPixelsPerUnit = -1/3 }

    function wheelEventDelta(e) {
        var dx = e.wheelDeltaX, dy = e.wheelDeltaY
        if (dx == null && e.detail && e.axis == e.HORIZONTAL_AXIS) { dx = e.detail }
        if (dy == null && e.detail && e.axis == e.VERTICAL_AXIS) { dy = e.detail }
        else if (dy == null) { dy = e.wheelDelta }
        return {x: dx, y: dy}
    }
    function wheelEventPixels(e) {
        var delta = wheelEventDelta(e)
        delta.x *= wheelPixelsPerUnit
        delta.y *= wheelPixelsPerUnit
        return delta
    }

    function onScrollWheel(cm, e) {
        var delta = wheelEventDelta(e), dx = delta.x, dy = delta.y

        var display = cm.display, scroll = display.scroller
        // Quit if there's nothing to scroll here
        var canScrollX = scroll.scrollWidth > scroll.clientWidth
        var canScrollY = scroll.scrollHeight > scroll.clientHeight
        if (!(dx && canScrollX || dy && canScrollY)) { return }

        // Webkit browsers on OS X abort momentum scrolls when the target
        // of the scroll event is removed from the scrollable element.
        // This hack (see related code in patchDisplay) makes sure the
        // element is kept around.
        if (dy && mac && webkit) {
            outer: for (var cur = e.target, view = display.view; cur != scroll; cur = cur.parentNode) {
                for (var i = 0; i < view.length; i++) {
                    if (view[i].node == cur) {
                        cm.display.currentWheelTarget = cur
                        break outer
                    }
                }
            }
        }

        // On some browsers, horizontal scrolling will cause redraws to
        // happen before the gutter has been realigned, causing it to
        // wriggle around in a most unseemly way. When we have an
        // estimated pixels/delta value, we just handle horizontal
        // scrolling entirely here. It'll be slightly off from native, but
        // better than glitching out.
        if (dx && !gecko && !presto && wheelPixelsPerUnit != null) {
            if (dy && canScrollY)
            { setScrollTop(cm, Math.max(0, Math.min(scroll.scrollTop + dy * wheelPixelsPerUnit, scroll.scrollHeight - scroll.clientHeight))) }
            setScrollLeft(cm, Math.max(0, Math.min(scroll.scrollLeft + dx * wheelPixelsPerUnit, scroll.scrollWidth - scroll.clientWidth)))
            // Only prevent default scrolling if vertical scrolling is
            // actually possible. Otherwise, it causes vertical scroll
            // jitter on OSX trackpads when deltaX is small and deltaY
            // is large (issue #3579)
            if (!dy || (dy && canScrollY))
            { e_preventDefault(e) }
            display.wheelStartX = null // Abort measurement, if in progress
            return
        }

        // 'Project' the visible viewport to cover the area that is being
        // scrolled into view (if we know enough to estimate it).
        if (dy && wheelPixelsPerUnit != null) {
            var pixels = dy * wheelPixelsPerUnit
            var top = cm.doc.scrollTop, bot = top + display.wrapper.clientHeight
            if (pixels < 0) { top = Math.max(0, top + pixels - 50) }
            else { bot = Math.min(cm.doc.height, bot + pixels + 50) }
            updateDisplaySimple(cm, {top: top, bottom: bot})
        }

        if (wheelSamples < 20) {
            if (display.wheelStartX == null) {
                display.wheelStartX = scroll.scrollLeft; display.wheelStartY = scroll.scrollTop
                display.wheelDX = dx; display.wheelDY = dy
                setTimeout(function () {
                    if (display.wheelStartX == null) { return }
                    var movedX = scroll.scrollLeft - display.wheelStartX
                    var movedY = scroll.scrollTop - display.wheelStartY
                    var sample = (movedY && display.wheelDY && movedY / display.wheelDY) ||
                        (movedX && display.wheelDX && movedX / display.wheelDX)
                    display.wheelStartX = display.wheelStartY = null
                    if (!sample) { return }
                    wheelPixelsPerUnit = (wheelPixelsPerUnit * wheelSamples + sample) / (wheelSamples + 1)
                    ++wheelSamples
                }, 200)
            } else {
                display.wheelDX += dx; display.wheelDY += dy
            }
        }
    }

// SCROLLBARS

// Prepare DOM reads needed to update the scrollbars. Done in one
// shot to minimize update/measure roundtrips.
    function measureForScrollbars(cm) {
        var d = cm.display, gutterW = d.gutters.offsetWidth
        var docH = Math.round(cm.doc.height + paddingVert(cm.display))
        return {
            clientHeight: d.scroller.clientHeight,
            viewHeight: d.wrapper.clientHeight,
            scrollWidth: d.scroller.scrollWidth, clientWidth: d.scroller.clientWidth,
            viewWidth: d.wrapper.clientWidth,
            barLeft: cm.options.fixedGutter ? gutterW : 0,
            docHeight: docH,
            scrollHeight: docH + scrollGap(cm) + d.barHeight,
            nativeBarWidth: d.nativeBarWidth,
            gutterWidth: gutterW
        }
    }

    function NativeScrollbars(place, scroll, cm) {
        this.cm = cm
        var vert = this.vert = elt("div", [elt("div", null, null, "min-width: 1px")], "CodeMirror-vscrollbar")
        var horiz = this.horiz = elt("div", [elt("div", null, null, "height: 100%; min-height: 1px")], "CodeMirror-hscrollbar")
        place(vert); place(horiz)

        on(vert, "scroll", function () {
            if (vert.clientHeight) { scroll(vert.scrollTop, "vertical") }
        })
        on(horiz, "scroll", function () {
            if (horiz.clientWidth) { scroll(horiz.scrollLeft, "horizontal") }
        })

        this.checkedZeroWidth = false
        // Need to set a minimum width to see the scrollbar on IE7 (but must not set it on IE8).
        if (ie && ie_version < 8) { this.horiz.style.minHeight = this.vert.style.minWidth = "18px" }
    }

    NativeScrollbars.prototype = copyObj({
        update: function(measure) {
            var needsH = measure.scrollWidth > measure.clientWidth + 1
            var needsV = measure.scrollHeight > measure.clientHeight + 1
            var sWidth = measure.nativeBarWidth

            if (needsV) {
                this.vert.style.display = "block"
                this.vert.style.bottom = needsH ? sWidth + "px" : "0"
                var totalHeight = measure.viewHeight - (needsH ? sWidth : 0)
                // A bug in IE8 can cause this value to be negative, so guard it.
                this.vert.firstChild.style.height =
                    Math.max(0, measure.scrollHeight - measure.clientHeight + totalHeight) + "px"
            } else {
                this.vert.style.display = ""
                this.vert.firstChild.style.height = "0"
            }

            if (needsH) {
                this.horiz.style.display = "block"
                this.horiz.style.right = needsV ? sWidth + "px" : "0"
                this.horiz.style.left = measure.barLeft + "px"
                var totalWidth = measure.viewWidth - measure.barLeft - (needsV ? sWidth : 0)
                this.horiz.firstChild.style.width =
                    (measure.scrollWidth - measure.clientWidth + totalWidth) + "px"
            } else {
                this.horiz.style.display = ""
                this.horiz.firstChild.style.width = "0"
            }

            if (!this.checkedZeroWidth && measure.clientHeight > 0) {
                if (sWidth == 0) { this.zeroWidthHack() }
                this.checkedZeroWidth = true
            }

            return {right: needsV ? sWidth : 0, bottom: needsH ? sWidth : 0}
        },
        setScrollLeft: function(pos) {
            if (this.horiz.scrollLeft != pos) { this.horiz.scrollLeft = pos }
            if (this.disableHoriz) { this.enableZeroWidthBar(this.horiz, this.disableHoriz) }
        },
        setScrollTop: function(pos) {
            if (this.vert.scrollTop != pos) { this.vert.scrollTop = pos }
            if (this.disableVert) { this.enableZeroWidthBar(this.vert, this.disableVert) }
        },
        zeroWidthHack: function() {
            var w = mac && !mac_geMountainLion ? "12px" : "18px"
            this.horiz.style.height = this.vert.style.width = w
            this.horiz.style.pointerEvents = this.vert.style.pointerEvents = "none"
            this.disableHoriz = new Delayed
            this.disableVert = new Delayed
        },
        enableZeroWidthBar: function(bar, delay) {
            bar.style.pointerEvents = "auto"
            function maybeDisable() {
                // To find out whether the scrollbar is still visible, we
                // check whether the element under the pixel in the bottom
                // left corner of the scrollbar box is the scrollbar box
                // itself (when the bar is still visible) or its filler child
                // (when the bar is hidden). If it is still visible, we keep
                // it enabled, if it's hidden, we disable pointer events.
                var box = bar.getBoundingClientRect()
                var elt$$1 = document.elementFromPoint(box.left + 1, box.bottom - 1)
                if (elt$$1 != bar) { bar.style.pointerEvents = "none" }
                else { delay.set(1000, maybeDisable) }
            }
            delay.set(1000, maybeDisable)
        },
        clear: function() {
            var parent = this.horiz.parentNode
            parent.removeChild(this.horiz)
            parent.removeChild(this.vert)
        }
    }, NativeScrollbars.prototype)

    function NullScrollbars() {}

    NullScrollbars.prototype = copyObj({
        update: function() { return {bottom: 0, right: 0} },
        setScrollLeft: function() {},
        setScrollTop: function() {},
        clear: function() {}
    }, NullScrollbars.prototype)

    function updateScrollbars(cm, measure) {
        if (!measure) { measure = measureForScrollbars(cm) }
        var startWidth = cm.display.barWidth, startHeight = cm.display.barHeight
        updateScrollbarsInner(cm, measure)
        for (var i = 0; i < 4 && startWidth != cm.display.barWidth || startHeight != cm.display.barHeight; i++) {
            if (startWidth != cm.display.barWidth && cm.options.lineWrapping)
            { updateHeightsInViewport(cm) }
            updateScrollbarsInner(cm, measureForScrollbars(cm))
            startWidth = cm.display.barWidth; startHeight = cm.display.barHeight
        }
    }

// Re-synchronize the fake scrollbars with the actual size of the
// content.
    function updateScrollbarsInner(cm, measure) {
        var d = cm.display
        var sizes = d.scrollbars.update(measure)

        d.sizer.style.paddingRight = (d.barWidth = sizes.right) + "px"
        d.sizer.style.paddingBottom = (d.barHeight = sizes.bottom) + "px"
        d.heightForcer.style.borderBottom = sizes.bottom + "px solid transparent"

        if (sizes.right && sizes.bottom) {
            d.scrollbarFiller.style.display = "block"
            d.scrollbarFiller.style.height = sizes.bottom + "px"
            d.scrollbarFiller.style.width = sizes.right + "px"
        } else { d.scrollbarFiller.style.display = "" }
        if (sizes.bottom && cm.options.coverGutterNextToScrollbar && cm.options.fixedGutter) {
            d.gutterFiller.style.display = "block"
            d.gutterFiller.style.height = sizes.bottom + "px"
            d.gutterFiller.style.width = measure.gutterWidth + "px"
        } else { d.gutterFiller.style.display = "" }
    }

    var scrollbarModel = {"native": NativeScrollbars, "null": NullScrollbars}

    function initScrollbars(cm) {
        if (cm.display.scrollbars) {
            cm.display.scrollbars.clear()
            if (cm.display.scrollbars.addClass)
            { rmClass(cm.display.wrapper, cm.display.scrollbars.addClass) }
        }

        cm.display.scrollbars = new scrollbarModel[cm.options.scrollbarStyle](function (node) {
            cm.display.wrapper.insertBefore(node, cm.display.scrollbarFiller)
            // Prevent clicks in the scrollbars from killing focus
            on(node, "mousedown", function () {
                if (cm.state.focused) { setTimeout(function () { return cm.display.input.focus(); }, 0) }
            })
            node.setAttribute("cm-not-content", "true")
        }, function (pos, axis) {
            if (axis == "horizontal") { setScrollLeft(cm, pos) }
            else { setScrollTop(cm, pos) }
        }, cm)
        if (cm.display.scrollbars.addClass)
        { addClass(cm.display.wrapper, cm.display.scrollbars.addClass) }
    }

// SCROLLING THINGS INTO VIEW

// If an editor sits on the top or bottom of the window, partially
// scrolled out of view, this ensures that the cursor is visible.
    function maybeScrollWindow(cm, coords) {
        if (signalDOMEvent(cm, "scrollCursorIntoView")) { return }

        var display = cm.display, box = display.sizer.getBoundingClientRect(), doScroll = null
        if (coords.top + box.top < 0) { doScroll = true }
        else if (coords.bottom + box.top > (window.innerHeight || document.documentElement.clientHeight)) { doScroll = false }
        if (doScroll != null && !phantom) {
            var scrollNode = elt("div", "\u200b", null, ("position: absolute;\n                         top: " + (coords.top - display.viewOffset - paddingTop(cm.display)) + "px;\n                         height: " + (coords.bottom - coords.top + scrollGap(cm) + display.barHeight) + "px;\n                         left: " + (coords.left) + "px; width: 2px;"))
            cm.display.lineSpace.appendChild(scrollNode)
            scrollNode.scrollIntoView(doScroll)
            cm.display.lineSpace.removeChild(scrollNode)
        }
    }

// Scroll a given position into view (immediately), verifying that
// it actually became visible (as line heights are accurately
// measured, the position of something may 'drift' during drawing).
    function scrollPosIntoView(cm, pos, end, margin) {
        if (margin == null) { margin = 0 }
        var coords
        for (var limit = 0; limit < 5; limit++) {
            var changed = false
            coords = cursorCoords(cm, pos)
            var endCoords = !end || end == pos ? coords : cursorCoords(cm, end)
            var scrollPos = calculateScrollPos(cm, Math.min(coords.left, endCoords.left),
                Math.min(coords.top, endCoords.top) - margin,
                Math.max(coords.left, endCoords.left),
                Math.max(coords.bottom, endCoords.bottom) + margin)
            var startTop = cm.doc.scrollTop, startLeft = cm.doc.scrollLeft
            if (scrollPos.scrollTop != null) {
                setScrollTop(cm, scrollPos.scrollTop)
                if (Math.abs(cm.doc.scrollTop - startTop) > 1) { changed = true }
            }
            if (scrollPos.scrollLeft != null) {
                setScrollLeft(cm, scrollPos.scrollLeft)
                if (Math.abs(cm.doc.scrollLeft - startLeft) > 1) { changed = true }
            }
            if (!changed) { break }
        }
        return coords
    }

// Scroll a given set of coordinates into view (immediately).
    function scrollIntoView(cm, x1, y1, x2, y2) {
        var scrollPos = calculateScrollPos(cm, x1, y1, x2, y2)
        if (scrollPos.scrollTop != null) { setScrollTop(cm, scrollPos.scrollTop) }
        if (scrollPos.scrollLeft != null) { setScrollLeft(cm, scrollPos.scrollLeft) }
    }

// Calculate a new scroll position needed to scroll the given
// rectangle into view. Returns an object with scrollTop and
// scrollLeft properties. When these are undefined, the
// vertical/horizontal position does not need to be adjusted.
    function calculateScrollPos(cm, x1, y1, x2, y2) {
        var display = cm.display, snapMargin = textHeight(cm.display)
        if (y1 < 0) { y1 = 0 }
        var screentop = cm.curOp && cm.curOp.scrollTop != null ? cm.curOp.scrollTop : display.scroller.scrollTop
        var screen = displayHeight(cm), result = {}
        if (y2 - y1 > screen) { y2 = y1 + screen }
        var docBottom = cm.doc.height + paddingVert(display)
        var atTop = y1 < snapMargin, atBottom = y2 > docBottom - snapMargin
        if (y1 < screentop) {
            result.scrollTop = atTop ? 0 : y1
        } else if (y2 > screentop + screen) {
            var newTop = Math.min(y1, (atBottom ? docBottom : y2) - screen)
            if (newTop != screentop) { result.scrollTop = newTop }
        }

        var screenleft = cm.curOp && cm.curOp.scrollLeft != null ? cm.curOp.scrollLeft : display.scroller.scrollLeft
        var screenw = displayWidth(cm) - (cm.options.fixedGutter ? display.gutters.offsetWidth : 0)
        var tooWide = x2 - x1 > screenw
        if (tooWide) { x2 = x1 + screenw }
        if (x1 < 10)
        { result.scrollLeft = 0 }
        else if (x1 < screenleft)
        { result.scrollLeft = Math.max(0, x1 - (tooWide ? 0 : 10)) }
        else if (x2 > screenw + screenleft - 3)
        { result.scrollLeft = x2 + (tooWide ? 0 : 10) - screenw }
        return result
    }

// Store a relative adjustment to the scroll position in the current
// operation (to be applied when the operation finishes).
    function addToScrollPos(cm, left, top) {
        if (left != null || top != null) { resolveScrollToPos(cm) }
        if (left != null)
        { cm.curOp.scrollLeft = (cm.curOp.scrollLeft == null ? cm.doc.scrollLeft : cm.curOp.scrollLeft) + left }
        if (top != null)
        { cm.curOp.scrollTop = (cm.curOp.scrollTop == null ? cm.doc.scrollTop : cm.curOp.scrollTop) + top }
    }

// Make sure that at the end of the operation the current cursor is
// shown.
    function ensureCursorVisible(cm) {
        resolveScrollToPos(cm)
        var cur = cm.getCursor(), from = cur, to = cur
        if (!cm.options.lineWrapping) {
            from = cur.ch ? Pos(cur.line, cur.ch - 1) : cur
            to = Pos(cur.line, cur.ch + 1)
        }
        cm.curOp.scrollToPos = {from: from, to: to, margin: cm.options.cursorScrollMargin, isCursor: true}
    }

// When an operation has its scrollToPos property set, and another
// scroll action is applied before the end of the operation, this
// 'simulates' scrolling that position into view in a cheap way, so
// that the effect of intermediate scroll commands is not ignored.
    function resolveScrollToPos(cm) {
        var range$$1 = cm.curOp.scrollToPos
        if (range$$1) {
            cm.curOp.scrollToPos = null
            var from = estimateCoords(cm, range$$1.from), to = estimateCoords(cm, range$$1.to)
            var sPos = calculateScrollPos(cm, Math.min(from.left, to.left),
                Math.min(from.top, to.top) - range$$1.margin,
                Math.max(from.right, to.right),
                Math.max(from.bottom, to.bottom) + range$$1.margin)
            cm.scrollTo(sPos.scrollLeft, sPos.scrollTop)
        }
    }

// Operations are used to wrap a series of changes to the editor
// state in such a way that each change won't have to update the
// cursor and display (which would be awkward, slow, and
// error-prone). Instead, display updates are batched and then all
// combined and executed at once.

    var nextOpId = 0
// Start a new operation.
    function startOperation(cm) {
        cm.curOp = {
            cm: cm,
            viewChanged: false,      // Flag that indicates that lines might need to be redrawn
            startHeight: cm.doc.height, // Used to detect need to update scrollbar
            forceUpdate: false,      // Used to force a redraw
            updateInput: null,       // Whether to reset the input textarea
            typing: false,           // Whether this reset should be careful to leave existing text (for compositing)
            changeObjs: null,        // Accumulated changes, for firing change events
            cursorActivityHandlers: null, // Set of handlers to fire cursorActivity on
            cursorActivityCalled: 0, // Tracks which cursorActivity handlers have been called already
            selectionChanged: false, // Whether the selection needs to be redrawn
            updateMaxLine: false,    // Set when the widest line needs to be determined anew
            scrollLeft: null, scrollTop: null, // Intermediate scroll position, not pushed to DOM yet
            scrollToPos: null,       // Used to scroll to a specific position
            focus: false,
            id: ++nextOpId           // Unique ID
        }
        pushOperation(cm.curOp)
    }

// Finish an operation, updating the display and signalling delayed events
    function endOperation(cm) {
        var op = cm.curOp
        finishOperation(op, function (group) {
            for (var i = 0; i < group.ops.length; i++)
            { group.ops[i].cm.curOp = null }
            endOperations(group)
        })
    }

// The DOM updates done when an operation finishes are batched so
// that the minimum number of relayouts are required.
    function endOperations(group) {
        var ops = group.ops
        for (var i = 0; i < ops.length; i++) // Read DOM
        { endOperation_R1(ops[i]) }
        for (var i$1 = 0; i$1 < ops.length; i$1++) // Write DOM (maybe)
        { endOperation_W1(ops[i$1]) }
        for (var i$2 = 0; i$2 < ops.length; i$2++) // Read DOM
        { endOperation_R2(ops[i$2]) }
        for (var i$3 = 0; i$3 < ops.length; i$3++) // Write DOM (maybe)
        { endOperation_W2(ops[i$3]) }
        for (var i$4 = 0; i$4 < ops.length; i$4++) // Read DOM
        { endOperation_finish(ops[i$4]) }
    }

    function endOperation_R1(op) {
        var cm = op.cm, display = cm.display
        maybeClipScrollbars(cm)
        if (op.updateMaxLine) { findMaxLine(cm) }

        op.mustUpdate = op.viewChanged || op.forceUpdate || op.scrollTop != null ||
            op.scrollToPos && (op.scrollToPos.from.line < display.viewFrom ||
            op.scrollToPos.to.line >= display.viewTo) ||
            display.maxLineChanged && cm.options.lineWrapping
        op.update = op.mustUpdate &&
            new DisplayUpdate(cm, op.mustUpdate && {top: op.scrollTop, ensure: op.scrollToPos}, op.forceUpdate)
    }

    function endOperation_W1(op) {
        op.updatedDisplay = op.mustUpdate && updateDisplayIfNeeded(op.cm, op.update)
    }

    function endOperation_R2(op) {
        var cm = op.cm, display = cm.display
        if (op.updatedDisplay) { updateHeightsInViewport(cm) }

        op.barMeasure = measureForScrollbars(cm)

        // If the max line changed since it was last measured, measure it,
        // and ensure the document's width matches it.
        // updateDisplay_W2 will use these properties to do the actual resizing
        if (display.maxLineChanged && !cm.options.lineWrapping) {
            op.adjustWidthTo = measureChar(cm, display.maxLine, display.maxLine.text.length).left + 3
            cm.display.sizerWidth = op.adjustWidthTo
            op.barMeasure.scrollWidth =
                Math.max(display.scroller.clientWidth, display.sizer.offsetLeft + op.adjustWidthTo + scrollGap(cm) + cm.display.barWidth)
            op.maxScrollLeft = Math.max(0, display.sizer.offsetLeft + op.adjustWidthTo - displayWidth(cm))
        }

        if (op.updatedDisplay || op.selectionChanged)
        { op.preparedSelection = display.input.prepareSelection(op.focus) }
    }

    function endOperation_W2(op) {
        var cm = op.cm

        if (op.adjustWidthTo != null) {
            cm.display.sizer.style.minWidth = op.adjustWidthTo + "px"
            if (op.maxScrollLeft < cm.doc.scrollLeft)
            { setScrollLeft(cm, Math.min(cm.display.scroller.scrollLeft, op.maxScrollLeft), true) }
            cm.display.maxLineChanged = false
        }

        var takeFocus = op.focus && op.focus == activeElt() && (!document.hasFocus || document.hasFocus())
        if (op.preparedSelection)
        { cm.display.input.showSelection(op.preparedSelection, takeFocus) }
        if (op.updatedDisplay || op.startHeight != cm.doc.height)
        { updateScrollbars(cm, op.barMeasure) }
        if (op.updatedDisplay)
        { setDocumentHeight(cm, op.barMeasure) }

        if (op.selectionChanged) { restartBlink(cm) }

        if (cm.state.focused && op.updateInput)
        { cm.display.input.reset(op.typing) }
        if (takeFocus) { ensureFocus(op.cm) }
    }

    function endOperation_finish(op) {
        var cm = op.cm, display = cm.display, doc = cm.doc

        if (op.updatedDisplay) { postUpdateDisplay(cm, op.update) }

        // Abort mouse wheel delta measurement, when scrolling explicitly
        if (display.wheelStartX != null && (op.scrollTop != null || op.scrollLeft != null || op.scrollToPos))
        { display.wheelStartX = display.wheelStartY = null }

        // Propagate the scroll position to the actual DOM scroller
        if (op.scrollTop != null && (display.scroller.scrollTop != op.scrollTop || op.forceScroll)) {
            doc.scrollTop = Math.max(0, Math.min(display.scroller.scrollHeight - display.scroller.clientHeight, op.scrollTop))
            display.scrollbars.setScrollTop(doc.scrollTop)
            display.scroller.scrollTop = doc.scrollTop
        }
        if (op.scrollLeft != null && (display.scroller.scrollLeft != op.scrollLeft || op.forceScroll)) {
            doc.scrollLeft = Math.max(0, Math.min(display.scroller.scrollWidth - display.scroller.clientWidth, op.scrollLeft))
            display.scrollbars.setScrollLeft(doc.scrollLeft)
            display.scroller.scrollLeft = doc.scrollLeft
            alignHorizontally(cm)
        }
        // If we need to scroll a specific position into view, do so.
        if (op.scrollToPos) {
            var coords = scrollPosIntoView(cm, clipPos(doc, op.scrollToPos.from),
                clipPos(doc, op.scrollToPos.to), op.scrollToPos.margin)
            if (op.scrollToPos.isCursor && cm.state.focused) { maybeScrollWindow(cm, coords) }
        }

        // Fire events for markers that are hidden/unidden by editing or
        // undoing
        var hidden = op.maybeHiddenMarkers, unhidden = op.maybeUnhiddenMarkers
        if (hidden) { for (var i = 0; i < hidden.length; ++i)
        { if (!hidden[i].lines.length) { signal(hidden[i], "hide") } } }
        if (unhidden) { for (var i$1 = 0; i$1 < unhidden.length; ++i$1)
        { if (unhidden[i$1].lines.length) { signal(unhidden[i$1], "unhide") } } }

        if (display.wrapper.offsetHeight)
        { doc.scrollTop = cm.display.scroller.scrollTop }

        // Fire change events, and delayed event handlers
        if (op.changeObjs)
        { signal(cm, "changes", cm, op.changeObjs) }
        if (op.update)
        { op.update.finish() }
    }

// Run the given function in an operation
    function runInOp(cm, f) {
        if (cm.curOp) { return f() }
        startOperation(cm)
        try { return f() }
        finally { endOperation(cm) }
    }
// Wraps a function in an operation. Returns the wrapped function.
    function operation(cm, f) {
        return function() {
            if (cm.curOp) { return f.apply(cm, arguments) }
            startOperation(cm)
            try { return f.apply(cm, arguments) }
            finally { endOperation(cm) }
        }
    }
// Used to add methods to editor and doc instances, wrapping them in
// operations.
    function methodOp(f) {
        return function() {
            if (this.curOp) { return f.apply(this, arguments) }
            startOperation(this)
            try { return f.apply(this, arguments) }
            finally { endOperation(this) }
        }
    }
    function docMethodOp(f) {
        return function() {
            var cm = this.cm
            if (!cm || cm.curOp) { return f.apply(this, arguments) }
            startOperation(cm)
            try { return f.apply(this, arguments) }
            finally { endOperation(cm) }
        }
    }

// Updates the display.view data structure for a given change to the
// document. From and to are in pre-change coordinates. Lendiff is
// the amount of lines added or subtracted by the change. This is
// used for changes that span multiple lines, or change the way
// lines are divided into visual lines. regLineChange (below)
// registers single-line changes.
    function regChange(cm, from, to, lendiff) {
        if (from == null) { from = cm.doc.first }
        if (to == null) { to = cm.doc.first + cm.doc.size }
        if (!lendiff) { lendiff = 0 }

        var display = cm.display
        if (lendiff && to < display.viewTo &&
            (display.updateLineNumbers == null || display.updateLineNumbers > from))
        { display.updateLineNumbers = from }

        cm.curOp.viewChanged = true

        if (from >= display.viewTo) { // Change after
            if (sawCollapsedSpans && visualLineNo(cm.doc, from) < display.viewTo)
            { resetView(cm) }
        } else if (to <= display.viewFrom) { // Change before
            if (sawCollapsedSpans && visualLineEndNo(cm.doc, to + lendiff) > display.viewFrom) {
                resetView(cm)
            } else {
                display.viewFrom += lendiff
                display.viewTo += lendiff
            }
        } else if (from <= display.viewFrom && to >= display.viewTo) { // Full overlap
            resetView(cm)
        } else if (from <= display.viewFrom) { // Top overlap
            var cut = viewCuttingPoint(cm, to, to + lendiff, 1)
            if (cut) {
                display.view = display.view.slice(cut.index)
                display.viewFrom = cut.lineN
                display.viewTo += lendiff
            } else {
                resetView(cm)
            }
        } else if (to >= display.viewTo) { // Bottom overlap
            var cut$1 = viewCuttingPoint(cm, from, from, -1)
            if (cut$1) {
                display.view = display.view.slice(0, cut$1.index)
                display.viewTo = cut$1.lineN
            } else {
                resetView(cm)
            }
        } else { // Gap in the middle
            var cutTop = viewCuttingPoint(cm, from, from, -1)
            var cutBot = viewCuttingPoint(cm, to, to + lendiff, 1)
            if (cutTop && cutBot) {
                display.view = display.view.slice(0, cutTop.index)
                    .concat(buildViewArray(cm, cutTop.lineN, cutBot.lineN))
                    .concat(display.view.slice(cutBot.index))
                display.viewTo += lendiff
            } else {
                resetView(cm)
            }
        }

        var ext = display.externalMeasured
        if (ext) {
            if (to < ext.lineN)
            { ext.lineN += lendiff }
            else if (from < ext.lineN + ext.size)
            { display.externalMeasured = null }
        }
    }

// Register a change to a single line. Type must be one of "text",
// "gutter", "class", "widget"
    function regLineChange(cm, line, type) {
        cm.curOp.viewChanged = true
        var display = cm.display, ext = cm.display.externalMeasured
        if (ext && line >= ext.lineN && line < ext.lineN + ext.size)
        { display.externalMeasured = null }

        if (line < display.viewFrom || line >= display.viewTo) { return }
        var lineView = display.view[findViewIndex(cm, line)]
        if (lineView.node == null) { return }
        var arr = lineView.changes || (lineView.changes = [])
        if (indexOf(arr, type) == -1) { arr.push(type) }
    }

// Clear the view.
    function resetView(cm) {
        cm.display.viewFrom = cm.display.viewTo = cm.doc.first
        cm.display.view = []
        cm.display.viewOffset = 0
    }

    function viewCuttingPoint(cm, oldN, newN, dir) {
        var index = findViewIndex(cm, oldN), diff, view = cm.display.view
        if (!sawCollapsedSpans || newN == cm.doc.first + cm.doc.size)
        { return {index: index, lineN: newN} }
        var n = cm.display.viewFrom
        for (var i = 0; i < index; i++)
        { n += view[i].size }
        if (n != oldN) {
            if (dir > 0) {
                if (index == view.length - 1) { return null }
                diff = (n + view[index].size) - oldN
                index++
            } else {
                diff = n - oldN
            }
            oldN += diff; newN += diff
        }
        while (visualLineNo(cm.doc, newN) != newN) {
            if (index == (dir < 0 ? 0 : view.length - 1)) { return null }
            newN += dir * view[index - (dir < 0 ? 1 : 0)].size
            index += dir
        }
        return {index: index, lineN: newN}
    }

// Force the view to cover a given range, adding empty view element
// or clipping off existing ones as needed.
    function adjustView(cm, from, to) {
        var display = cm.display, view = display.view
        if (view.length == 0 || from >= display.viewTo || to <= display.viewFrom) {
            display.view = buildViewArray(cm, from, to)
            display.viewFrom = from
        } else {
            if (display.viewFrom > from)
            { display.view = buildViewArray(cm, from, display.viewFrom).concat(display.view) }
            else if (display.viewFrom < from)
            { display.view = display.view.slice(findViewIndex(cm, from)) }
            display.viewFrom = from
            if (display.viewTo < to)
            { display.view = display.view.concat(buildViewArray(cm, display.viewTo, to)) }
            else if (display.viewTo > to)
            { display.view = display.view.slice(0, findViewIndex(cm, to)) }
        }
        display.viewTo = to
    }

// Count the number of lines in the view whose DOM representation is
// out of date (or nonexistent).
    function countDirtyView(cm) {
        var view = cm.display.view, dirty = 0
        for (var i = 0; i < view.length; i++) {
            var lineView = view[i]
            if (!lineView.hidden && (!lineView.node || lineView.changes)) { ++dirty }
        }
        return dirty
    }

// HIGHLIGHT WORKER

    function startWorker(cm, time) {
        if (cm.doc.mode.startState && cm.doc.frontier < cm.display.viewTo)
        { cm.state.highlight.set(time, bind(highlightWorker, cm)) }
    }

    function highlightWorker(cm) {
        var doc = cm.doc
        if (doc.frontier < doc.first) { doc.frontier = doc.first }
        if (doc.frontier >= cm.display.viewTo) { return }
        var end = +new Date + cm.options.workTime
        var state = copyState(doc.mode, getStateBefore(cm, doc.frontier))
        var changedLines = []

        doc.iter(doc.frontier, Math.min(doc.first + doc.size, cm.display.viewTo + 500), function (line) {
            if (doc.frontier >= cm.display.viewFrom) { // Visible
                var oldStyles = line.styles, tooLong = line.text.length > cm.options.maxHighlightLength
                var highlighted = highlightLine(cm, line, tooLong ? copyState(doc.mode, state) : state, true)
                line.styles = highlighted.styles
                var oldCls = line.styleClasses, newCls = highlighted.classes
                if (newCls) { line.styleClasses = newCls }
                else if (oldCls) { line.styleClasses = null }
                var ischange = !oldStyles || oldStyles.length != line.styles.length ||
                    oldCls != newCls && (!oldCls || !newCls || oldCls.bgClass != newCls.bgClass || oldCls.textClass != newCls.textClass)
                for (var i = 0; !ischange && i < oldStyles.length; ++i) { ischange = oldStyles[i] != line.styles[i] }
                if (ischange) { changedLines.push(doc.frontier) }
                line.stateAfter = tooLong ? state : copyState(doc.mode, state)
            } else {
                if (line.text.length <= cm.options.maxHighlightLength)
                { processLine(cm, line.text, state) }
                line.stateAfter = doc.frontier % 5 == 0 ? copyState(doc.mode, state) : null
            }
            ++doc.frontier
            if (+new Date > end) {
                startWorker(cm, cm.options.workDelay)
                return true
            }
        })
        if (changedLines.length) { runInOp(cm, function () {
            for (var i = 0; i < changedLines.length; i++)
            { regLineChange(cm, changedLines[i], "text") }
        }) }
    }

// DISPLAY DRAWING

    function DisplayUpdate(cm, viewport, force) {
        var display = cm.display

        this.viewport = viewport
        // Store some values that we'll need later (but don't want to force a relayout for)
        this.visible = visibleLines(display, cm.doc, viewport)
        this.editorIsHidden = !display.wrapper.offsetWidth
        this.wrapperHeight = display.wrapper.clientHeight
        this.wrapperWidth = display.wrapper.clientWidth
        this.oldDisplayWidth = displayWidth(cm)
        this.force = force
        this.dims = getDimensions(cm)
        this.events = []
    }

    DisplayUpdate.prototype.signal = function(emitter, type) {
        if (hasHandler(emitter, type))
        { this.events.push(arguments) }
    }
    DisplayUpdate.prototype.finish = function() {
        var this$1 = this;

        for (var i = 0; i < this.events.length; i++)
        { signal.apply(null, this$1.events[i]) }
    }

    function maybeClipScrollbars(cm) {
        var display = cm.display
        if (!display.scrollbarsClipped && display.scroller.offsetWidth) {
            display.nativeBarWidth = display.scroller.offsetWidth - display.scroller.clientWidth
            display.heightForcer.style.height = scrollGap(cm) + "px"
            display.sizer.style.marginBottom = -display.nativeBarWidth + "px"
            display.sizer.style.borderRightWidth = scrollGap(cm) + "px"
            display.scrollbarsClipped = true
        }
    }

// Does the actual updating of the line display. Bails out
// (returning false) when there is nothing to be done and forced is
// false.
    function updateDisplayIfNeeded(cm, update) {
        var display = cm.display, doc = cm.doc

        if (update.editorIsHidden) {
            resetView(cm)
            return false
        }

        // Bail out if the visible area is already rendered and nothing changed.
        if (!update.force &&
            update.visible.from >= display.viewFrom && update.visible.to <= display.viewTo &&
            (display.updateLineNumbers == null || display.updateLineNumbers >= display.viewTo) &&
            display.renderedView == display.view && countDirtyView(cm) == 0)
        { return false }

        if (maybeUpdateLineNumberWidth(cm)) {
            resetView(cm)
            update.dims = getDimensions(cm)
        }

        // Compute a suitable new viewport (from & to)
        var end = doc.first + doc.size
        var from = Math.max(update.visible.from - cm.options.viewportMargin, doc.first)
        var to = Math.min(end, update.visible.to + cm.options.viewportMargin)
        if (display.viewFrom < from && from - display.viewFrom < 20) { from = Math.max(doc.first, display.viewFrom) }
        if (display.viewTo > to && display.viewTo - to < 20) { to = Math.min(end, display.viewTo) }
        if (sawCollapsedSpans) {
            from = visualLineNo(cm.doc, from)
            to = visualLineEndNo(cm.doc, to)
        }

        var different = from != display.viewFrom || to != display.viewTo ||
            display.lastWrapHeight != update.wrapperHeight || display.lastWrapWidth != update.wrapperWidth
        adjustView(cm, from, to)

        display.viewOffset = heightAtLine(getLine(cm.doc, display.viewFrom))
        // Position the mover div to align with the current scroll position
        cm.display.mover.style.top = display.viewOffset + "px"

        var toUpdate = countDirtyView(cm)
        if (!different && toUpdate == 0 && !update.force && display.renderedView == display.view &&
            (display.updateLineNumbers == null || display.updateLineNumbers >= display.viewTo))
        { return false }

        // For big changes, we hide the enclosing element during the
        // update, since that speeds up the operations on most browsers.
        var focused = activeElt()
        if (toUpdate > 4) { display.lineDiv.style.display = "none" }
        patchDisplay(cm, display.updateLineNumbers, update.dims)
        if (toUpdate > 4) { display.lineDiv.style.display = "" }
        display.renderedView = display.view
        // There might have been a widget with a focused element that got
        // hidden or updated, if so re-focus it.
        if (focused && activeElt() != focused && focused.offsetHeight) { focused.focus() }

        // Prevent selection and cursors from interfering with the scroll
        // width and height.
        removeChildren(display.cursorDiv)
        removeChildren(display.selectionDiv)
        display.gutters.style.height = display.sizer.style.minHeight = 0

        if (different) {
            display.lastWrapHeight = update.wrapperHeight
            display.lastWrapWidth = update.wrapperWidth
            startWorker(cm, 400)
        }

        display.updateLineNumbers = null

        return true
    }

    function postUpdateDisplay(cm, update) {
        var viewport = update.viewport

        for (var first = true;; first = false) {
            if (!first || !cm.options.lineWrapping || update.oldDisplayWidth == displayWidth(cm)) {
                // Clip forced viewport to actual scrollable area.
                if (viewport && viewport.top != null)
                { viewport = {top: Math.min(cm.doc.height + paddingVert(cm.display) - displayHeight(cm), viewport.top)} }
                // Updated line heights might result in the drawn area not
                // actually covering the viewport. Keep looping until it does.
                update.visible = visibleLines(cm.display, cm.doc, viewport)
                if (update.visible.from >= cm.display.viewFrom && update.visible.to <= cm.display.viewTo)
                { break }
            }
            if (!updateDisplayIfNeeded(cm, update)) { break }
            updateHeightsInViewport(cm)
            var barMeasure = measureForScrollbars(cm)
            updateSelection(cm)
            updateScrollbars(cm, barMeasure)
            setDocumentHeight(cm, barMeasure)
        }

        update.signal(cm, "update", cm)
        if (cm.display.viewFrom != cm.display.reportedViewFrom || cm.display.viewTo != cm.display.reportedViewTo) {
            update.signal(cm, "viewportChange", cm, cm.display.viewFrom, cm.display.viewTo)
            cm.display.reportedViewFrom = cm.display.viewFrom; cm.display.reportedViewTo = cm.display.viewTo
        }
    }

    function updateDisplaySimple(cm, viewport) {
        var update = new DisplayUpdate(cm, viewport)
        if (updateDisplayIfNeeded(cm, update)) {
            updateHeightsInViewport(cm)
            postUpdateDisplay(cm, update)
            var barMeasure = measureForScrollbars(cm)
            updateSelection(cm)
            updateScrollbars(cm, barMeasure)
            setDocumentHeight(cm, barMeasure)
            update.finish()
        }
    }

// Sync the actual display DOM structure with display.view, removing
// nodes for lines that are no longer in view, and creating the ones
// that are not there yet, and updating the ones that are out of
// date.
    function patchDisplay(cm, updateNumbersFrom, dims) {
        var display = cm.display, lineNumbers = cm.options.lineNumbers
        var container = display.lineDiv, cur = container.firstChild

        function rm(node) {
            var next = node.nextSibling
            // Works around a throw-scroll bug in OS X Webkit
            if (webkit && mac && cm.display.currentWheelTarget == node)
            { node.style.display = "none" }
            else
            { node.parentNode.removeChild(node) }
            return next
        }

        var view = display.view, lineN = display.viewFrom
        // Loop over the elements in the view, syncing cur (the DOM nodes
        // in display.lineDiv) with the view as we go.
        for (var i = 0; i < view.length; i++) {
            var lineView = view[i]
            if (lineView.hidden) {
            } else if (!lineView.node || lineView.node.parentNode != container) { // Not drawn yet
                var node = buildLineElement(cm, lineView, lineN, dims)
                container.insertBefore(node, cur)
            } else { // Already drawn
                while (cur != lineView.node) { cur = rm(cur) }
                var updateNumber = lineNumbers && updateNumbersFrom != null &&
                    updateNumbersFrom <= lineN && lineView.lineNumber
                if (lineView.changes) {
                    if (indexOf(lineView.changes, "gutter") > -1) { updateNumber = false }
                    updateLineForChanges(cm, lineView, lineN, dims)
                }
                if (updateNumber) {
                    removeChildren(lineView.lineNumber)
                    lineView.lineNumber.appendChild(document.createTextNode(lineNumberFor(cm.options, lineN)))
                }
                cur = lineView.node.nextSibling
            }
            lineN += lineView.size
        }
        while (cur) { cur = rm(cur) }
    }

    function updateGutterSpace(cm) {
        var width = cm.display.gutters.offsetWidth
        cm.display.sizer.style.marginLeft = width + "px"
    }

    function setDocumentHeight(cm, measure) {
        cm.display.sizer.style.minHeight = measure.docHeight + "px"
        cm.display.heightForcer.style.top = measure.docHeight + "px"
        cm.display.gutters.style.height = (measure.docHeight + cm.display.barHeight + scrollGap(cm)) + "px"
    }

// Rebuild the gutter elements, ensure the margin to the left of the
// code matches their width.
    function updateGutters(cm) {
        var gutters = cm.display.gutters, specs = cm.options.gutters
        removeChildren(gutters)
        var i = 0
        for (; i < specs.length; ++i) {
            var gutterClass = specs[i]
            var gElt = gutters.appendChild(elt("div", null, "CodeMirror-gutter " + gutterClass))
            if (gutterClass == "CodeMirror-linenumbers") {
                cm.display.lineGutter = gElt
                gElt.style.width = (cm.display.lineNumWidth || 1) + "px"
            }
        }
        gutters.style.display = i ? "" : "none"
        updateGutterSpace(cm)
    }

// Make sure the gutters options contains the element
// "CodeMirror-linenumbers" when the lineNumbers option is true.
    function setGuttersForLineNumbers(options) {
        var found = indexOf(options.gutters, "CodeMirror-linenumbers")
        if (found == -1 && options.lineNumbers) {
            options.gutters = options.gutters.concat(["CodeMirror-linenumbers"])
        } else if (found > -1 && !options.lineNumbers) {
            options.gutters = options.gutters.slice(0)
            options.gutters.splice(found, 1)
        }
    }

// Selection objects are immutable. A new one is created every time
// the selection changes. A selection is one or more non-overlapping
// (and non-touching) ranges, sorted, and an integer that indicates
// which one is the primary selection (the one that's scrolled into
// view, that getCursor returns, etc).
    function Selection(ranges, primIndex) {
        this.ranges = ranges
        this.primIndex = primIndex
    }

    Selection.prototype = {
        primary: function() { return this.ranges[this.primIndex] },
        equals: function(other) {
            var this$1 = this;

            if (other == this) { return true }
            if (other.primIndex != this.primIndex || other.ranges.length != this.ranges.length) { return false }
            for (var i = 0; i < this.ranges.length; i++) {
                var here = this$1.ranges[i], there = other.ranges[i]
                if (cmp(here.anchor, there.anchor) != 0 || cmp(here.head, there.head) != 0) { return false }
            }
            return true
        },
        deepCopy: function() {
            var this$1 = this;

            var out = []
            for (var i = 0; i < this.ranges.length; i++)
            { out[i] = new Range(copyPos(this$1.ranges[i].anchor), copyPos(this$1.ranges[i].head)) }
            return new Selection(out, this.primIndex)
        },
        somethingSelected: function() {
            var this$1 = this;

            for (var i = 0; i < this.ranges.length; i++)
            { if (!this$1.ranges[i].empty()) { return true } }
            return false
        },
        contains: function(pos, end) {
            var this$1 = this;

            if (!end) { end = pos }
            for (var i = 0; i < this.ranges.length; i++) {
                var range = this$1.ranges[i]
                if (cmp(end, range.from()) >= 0 && cmp(pos, range.to()) <= 0)
                { return i }
            }
            return -1
        }
    }

    function Range(anchor, head) {
        this.anchor = anchor; this.head = head
    }

    Range.prototype = {
        from: function() { return minPos(this.anchor, this.head) },
        to: function() { return maxPos(this.anchor, this.head) },
        empty: function() {
            return this.head.line == this.anchor.line && this.head.ch == this.anchor.ch
        }
    }

// Take an unsorted, potentially overlapping set of ranges, and
// build a selection out of it. 'Consumes' ranges array (modifying
// it).
    function normalizeSelection(ranges, primIndex) {
        var prim = ranges[primIndex]
        ranges.sort(function (a, b) { return cmp(a.from(), b.from()); })
        primIndex = indexOf(ranges, prim)
        for (var i = 1; i < ranges.length; i++) {
            var cur = ranges[i], prev = ranges[i - 1]
            if (cmp(prev.to(), cur.from()) >= 0) {
                var from = minPos(prev.from(), cur.from()), to = maxPos(prev.to(), cur.to())
                var inv = prev.empty() ? cur.from() == cur.head : prev.from() == prev.head
                if (i <= primIndex) { --primIndex }
                ranges.splice(--i, 2, new Range(inv ? to : from, inv ? from : to))
            }
        }
        return new Selection(ranges, primIndex)
    }

    function simpleSelection(anchor, head) {
        return new Selection([new Range(anchor, head || anchor)], 0)
    }

// Compute the position of the end of a change (its 'to' property
// refers to the pre-change end).
    function changeEnd(change) {
        if (!change.text) { return change.to }
        return Pos(change.from.line + change.text.length - 1,
            lst(change.text).length + (change.text.length == 1 ? change.from.ch : 0))
    }

// Adjust a position to refer to the post-change position of the
// same text, or the end of the change if the change covers it.
    function adjustForChange(pos, change) {
        if (cmp(pos, change.from) < 0) { return pos }
        if (cmp(pos, change.to) <= 0) { return changeEnd(change) }

        var line = pos.line + change.text.length - (change.to.line - change.from.line) - 1, ch = pos.ch
        if (pos.line == change.to.line) { ch += changeEnd(change).ch - change.to.ch }
        return Pos(line, ch)
    }

    function computeSelAfterChange(doc, change) {
        var out = []
        for (var i = 0; i < doc.sel.ranges.length; i++) {
            var range = doc.sel.ranges[i]
            out.push(new Range(adjustForChange(range.anchor, change),
                adjustForChange(range.head, change)))
        }
        return normalizeSelection(out, doc.sel.primIndex)
    }

    function offsetPos(pos, old, nw) {
        if (pos.line == old.line)
        { return Pos(nw.line, pos.ch - old.ch + nw.ch) }
        else
        { return Pos(nw.line + (pos.line - old.line), pos.ch) }
    }

// Used by replaceSelections to allow moving the selection to the
// start or around the replaced test. Hint may be "start" or "around".
    function computeReplacedSel(doc, changes, hint) {
        var out = []
        var oldPrev = Pos(doc.first, 0), newPrev = oldPrev
        for (var i = 0; i < changes.length; i++) {
            var change = changes[i]
            var from = offsetPos(change.from, oldPrev, newPrev)
            var to = offsetPos(changeEnd(change), oldPrev, newPrev)
            oldPrev = change.to
            newPrev = to
            if (hint == "around") {
                var range = doc.sel.ranges[i], inv = cmp(range.head, range.anchor) < 0
                out[i] = new Range(inv ? to : from, inv ? from : to)
            } else {
                out[i] = new Range(from, from)
            }
        }
        return new Selection(out, doc.sel.primIndex)
    }

// Used to get the editor into a consistent state again when options change.

    function loadMode(cm) {
        cm.doc.mode = getMode(cm.options, cm.doc.modeOption)
        resetModeState(cm)
    }

    function resetModeState(cm) {
        cm.doc.iter(function (line) {
            if (line.stateAfter) { line.stateAfter = null }
            if (line.styles) { line.styles = null }
        })
        cm.doc.frontier = cm.doc.first
        startWorker(cm, 100)
        cm.state.modeGen++
        if (cm.curOp) { regChange(cm) }
    }

// DOCUMENT DATA STRUCTURE

// By default, updates that start and end at the beginning of a line
// are treated specially, in order to make the association of line
// widgets and marker elements with the text behave more intuitive.
    function isWholeLineUpdate(doc, change) {
        return change.from.ch == 0 && change.to.ch == 0 && lst(change.text) == "" &&
            (!doc.cm || doc.cm.options.wholeLineUpdateBefore)
    }

// Perform a change on the document data structure.
    function updateDoc(doc, change, markedSpans, estimateHeight$$1) {
        function spansFor(n) {return markedSpans ? markedSpans[n] : null}
        function update(line, text, spans) {
            updateLine(line, text, spans, estimateHeight$$1)
            signalLater(line, "change", line, change)
        }
        function linesFor(start, end) {
            var result = []
            for (var i = start; i < end; ++i)
            { result.push(new Line(text[i], spansFor(i), estimateHeight$$1)) }
            return result
        }

        var from = change.from, to = change.to, text = change.text
        var firstLine = getLine(doc, from.line), lastLine = getLine(doc, to.line)
        var lastText = lst(text), lastSpans = spansFor(text.length - 1), nlines = to.line - from.line

        // Adjust the line structure
        if (change.full) {
            doc.insert(0, linesFor(0, text.length))
            doc.remove(text.length, doc.size - text.length)
        } else if (isWholeLineUpdate(doc, change)) {
            // This is a whole-line replace. Treated specially to make
            // sure line objects move the way they are supposed to.
            var added = linesFor(0, text.length - 1)
            update(lastLine, lastLine.text, lastSpans)
            if (nlines) { doc.remove(from.line, nlines) }
            if (added.length) { doc.insert(from.line, added) }
        } else if (firstLine == lastLine) {
            if (text.length == 1) {
                update(firstLine, firstLine.text.slice(0, from.ch) + lastText + firstLine.text.slice(to.ch), lastSpans)
            } else {
                var added$1 = linesFor(1, text.length - 1)
                added$1.push(new Line(lastText + firstLine.text.slice(to.ch), lastSpans, estimateHeight$$1))
                update(firstLine, firstLine.text.slice(0, from.ch) + text[0], spansFor(0))
                doc.insert(from.line + 1, added$1)
            }
        } else if (text.length == 1) {
            update(firstLine, firstLine.text.slice(0, from.ch) + text[0] + lastLine.text.slice(to.ch), spansFor(0))
            doc.remove(from.line + 1, nlines)
        } else {
            update(firstLine, firstLine.text.slice(0, from.ch) + text[0], spansFor(0))
            update(lastLine, lastText + lastLine.text.slice(to.ch), lastSpans)
            var added$2 = linesFor(1, text.length - 1)
            if (nlines > 1) { doc.remove(from.line + 1, nlines - 1) }
            doc.insert(from.line + 1, added$2)
        }

        signalLater(doc, "change", doc, change)
    }

// Call f for all linked documents.
    function linkedDocs(doc, f, sharedHistOnly) {
        function propagate(doc, skip, sharedHist) {
            if (doc.linked) { for (var i = 0; i < doc.linked.length; ++i) {
                var rel = doc.linked[i]
                if (rel.doc == skip) { continue }
                var shared = sharedHist && rel.sharedHist
                if (sharedHistOnly && !shared) { continue }
                f(rel.doc, shared)
                propagate(rel.doc, doc, shared)
            } }
        }
        propagate(doc, null, true)
    }

// Attach a document to an editor.
    function attachDoc(cm, doc) {
        if (doc.cm) { throw new Error("This document is already in use.") }
        cm.doc = doc
        doc.cm = cm
        estimateLineHeights(cm)
        loadMode(cm)
        if (!cm.options.lineWrapping) { findMaxLine(cm) }
        cm.options.mode = doc.modeOption
        regChange(cm)
    }

    function History(startGen) {
        // Arrays of change events and selections. Doing something adds an
        // event to done and clears undo. Undoing moves events from done
        // to undone, redoing moves them in the other direction.
        this.done = []; this.undone = []
        this.undoDepth = Infinity
        // Used to track when changes can be merged into a single undo
        // event
        this.lastModTime = this.lastSelTime = 0
        this.lastOp = this.lastSelOp = null
        this.lastOrigin = this.lastSelOrigin = null
        // Used by the isClean() method
        this.generation = this.maxGeneration = startGen || 1
    }

// Create a history change event from an updateDoc-style change
// object.
    function historyChangeFromChange(doc, change) {
        var histChange = {from: copyPos(change.from), to: changeEnd(change), text: getBetween(doc, change.from, change.to)}
        attachLocalSpans(doc, histChange, change.from.line, change.to.line + 1)
        linkedDocs(doc, function (doc) { return attachLocalSpans(doc, histChange, change.from.line, change.to.line + 1); }, true)
        return histChange
    }

// Pop all selection events off the end of a history array. Stop at
// a change event.
    function clearSelectionEvents(array) {
        while (array.length) {
            var last = lst(array)
            if (last.ranges) { array.pop() }
            else { break }
        }
    }

// Find the top change event in the history. Pop off selection
// events that are in the way.
    function lastChangeEvent(hist, force) {
        if (force) {
            clearSelectionEvents(hist.done)
            return lst(hist.done)
        } else if (hist.done.length && !lst(hist.done).ranges) {
            return lst(hist.done)
        } else if (hist.done.length > 1 && !hist.done[hist.done.length - 2].ranges) {
            hist.done.pop()
            return lst(hist.done)
        }
    }

// Register a change in the history. Merges changes that are within
// a single operation, or are close together with an origin that
// allows merging (starting with "+") into a single event.
    function addChangeToHistory(doc, change, selAfter, opId) {
        var hist = doc.history
        hist.undone.length = 0
        var time = +new Date, cur
        var last

        if ((hist.lastOp == opId ||
            hist.lastOrigin == change.origin && change.origin &&
            ((change.origin.charAt(0) == "+" && doc.cm && hist.lastModTime > time - doc.cm.options.historyEventDelay) ||
            change.origin.charAt(0) == "*")) &&
            (cur = lastChangeEvent(hist, hist.lastOp == opId))) {
            // Merge this change into the last event
            last = lst(cur.changes)
            if (cmp(change.from, change.to) == 0 && cmp(change.from, last.to) == 0) {
                // Optimized case for simple insertion -- don't want to add
                // new changesets for every character typed
                last.to = changeEnd(change)
            } else {
                // Add new sub-event
                cur.changes.push(historyChangeFromChange(doc, change))
            }
        } else {
            // Can not be merged, start a new event.
            var before = lst(hist.done)
            if (!before || !before.ranges)
            { pushSelectionToHistory(doc.sel, hist.done) }
            cur = {changes: [historyChangeFromChange(doc, change)],
                generation: hist.generation}
            hist.done.push(cur)
            while (hist.done.length > hist.undoDepth) {
                hist.done.shift()
                if (!hist.done[0].ranges) { hist.done.shift() }
            }
        }
        hist.done.push(selAfter)
        hist.generation = ++hist.maxGeneration
        hist.lastModTime = hist.lastSelTime = time
        hist.lastOp = hist.lastSelOp = opId
        hist.lastOrigin = hist.lastSelOrigin = change.origin

        if (!last) { signal(doc, "historyAdded") }
    }

    function selectionEventCanBeMerged(doc, origin, prev, sel) {
        var ch = origin.charAt(0)
        return ch == "*" ||
            ch == "+" &&
            prev.ranges.length == sel.ranges.length &&
            prev.somethingSelected() == sel.somethingSelected() &&
            new Date - doc.history.lastSelTime <= (doc.cm ? doc.cm.options.historyEventDelay : 500)
    }

// Called whenever the selection changes, sets the new selection as
// the pending selection in the history, and pushes the old pending
// selection into the 'done' array when it was significantly
// different (in number of selected ranges, emptiness, or time).
    function addSelectionToHistory(doc, sel, opId, options) {
        var hist = doc.history, origin = options && options.origin

        // A new event is started when the previous origin does not match
        // the current, or the origins don't allow matching. Origins
        // starting with * are always merged, those starting with + are
        // merged when similar and close together in time.
        if (opId == hist.lastSelOp ||
            (origin && hist.lastSelOrigin == origin &&
            (hist.lastModTime == hist.lastSelTime && hist.lastOrigin == origin ||
            selectionEventCanBeMerged(doc, origin, lst(hist.done), sel))))
        { hist.done[hist.done.length - 1] = sel }
        else
        { pushSelectionToHistory(sel, hist.done) }

        hist.lastSelTime = +new Date
        hist.lastSelOrigin = origin
        hist.lastSelOp = opId
        if (options && options.clearRedo !== false)
        { clearSelectionEvents(hist.undone) }
    }

    function pushSelectionToHistory(sel, dest) {
        var top = lst(dest)
        if (!(top && top.ranges && top.equals(sel)))
        { dest.push(sel) }
    }

// Used to store marked span information in the history.
    function attachLocalSpans(doc, change, from, to) {
        var existing = change["spans_" + doc.id], n = 0
        doc.iter(Math.max(doc.first, from), Math.min(doc.first + doc.size, to), function (line) {
            if (line.markedSpans)
            { (existing || (existing = change["spans_" + doc.id] = {}))[n] = line.markedSpans }
            ++n
        })
    }

// When un/re-doing restores text containing marked spans, those
// that have been explicitly cleared should not be restored.
    function removeClearedSpans(spans) {
        if (!spans) { return null }
        var out
        for (var i = 0; i < spans.length; ++i) {
            if (spans[i].marker.explicitlyCleared) { if (!out) { out = spans.slice(0, i) } }
            else if (out) { out.push(spans[i]) }
        }
        return !out ? spans : out.length ? out : null
    }

// Retrieve and filter the old marked spans stored in a change event.
    function getOldSpans(doc, change) {
        var found = change["spans_" + doc.id]
        if (!found) { return null }
        var nw = []
        for (var i = 0; i < change.text.length; ++i)
        { nw.push(removeClearedSpans(found[i])) }
        return nw
    }

// Used for un/re-doing changes from the history. Combines the
// result of computing the existing spans with the set of spans that
// existed in the history (so that deleting around a span and then
// undoing brings back the span).
    function mergeOldSpans(doc, change) {
        var old = getOldSpans(doc, change)
        var stretched = stretchSpansOverChange(doc, change)
        if (!old) { return stretched }
        if (!stretched) { return old }

        for (var i = 0; i < old.length; ++i) {
            var oldCur = old[i], stretchCur = stretched[i]
            if (oldCur && stretchCur) {
                spans: for (var j = 0; j < stretchCur.length; ++j) {
                    var span = stretchCur[j]
                    for (var k = 0; k < oldCur.length; ++k)
                    { if (oldCur[k].marker == span.marker) { continue spans } }
                    oldCur.push(span)
                }
            } else if (stretchCur) {
                old[i] = stretchCur
            }
        }
        return old
    }

// Used both to provide a JSON-safe object in .getHistory, and, when
// detaching a document, to split the history in two
    function copyHistoryArray(events, newGroup, instantiateSel) {
        var copy = []
        for (var i = 0; i < events.length; ++i) {
            var event = events[i]
            if (event.ranges) {
                copy.push(instantiateSel ? Selection.prototype.deepCopy.call(event) : event)
                continue
            }
            var changes = event.changes, newChanges = []
            copy.push({changes: newChanges})
            for (var j = 0; j < changes.length; ++j) {
                var change = changes[j], m = void 0
                newChanges.push({from: change.from, to: change.to, text: change.text})
                if (newGroup) { for (var prop in change) { if (m = prop.match(/^spans_(\d+)$/)) {
                    if (indexOf(newGroup, Number(m[1])) > -1) {
                        lst(newChanges)[prop] = change[prop]
                        delete change[prop]
                    }
                } } }
            }
        }
        return copy
    }

// The 'scroll' parameter given to many of these indicated whether
// the new cursor position should be scrolled into view after
// modifying the selection.

// If shift is held or the extend flag is set, extends a range to
// include a given position (and optionally a second position).
// Otherwise, simply returns the range between the given positions.
// Used for cursor motion and such.
    function extendRange(doc, range, head, other) {
        if (doc.cm && doc.cm.display.shift || doc.extend) {
            var anchor = range.anchor
            if (other) {
                var posBefore = cmp(head, anchor) < 0
                if (posBefore != (cmp(other, anchor) < 0)) {
                    anchor = head
                    head = other
                } else if (posBefore != (cmp(head, other) < 0)) {
                    head = other
                }
            }
            return new Range(anchor, head)
        } else {
            return new Range(other || head, head)
        }
    }

// Extend the primary selection range, discard the rest.
    function extendSelection(doc, head, other, options) {
        setSelection(doc, new Selection([extendRange(doc, doc.sel.primary(), head, other)], 0), options)
    }

// Extend all selections (pos is an array of selections with length
// equal the number of selections)
    function extendSelections(doc, heads, options) {
        var out = []
        for (var i = 0; i < doc.sel.ranges.length; i++)
        { out[i] = extendRange(doc, doc.sel.ranges[i], heads[i], null) }
        var newSel = normalizeSelection(out, doc.sel.primIndex)
        setSelection(doc, newSel, options)
    }

// Updates a single range in the selection.
    function replaceOneSelection(doc, i, range, options) {
        var ranges = doc.sel.ranges.slice(0)
        ranges[i] = range
        setSelection(doc, normalizeSelection(ranges, doc.sel.primIndex), options)
    }

// Reset the selection to a single range.
    function setSimpleSelection(doc, anchor, head, options) {
        setSelection(doc, simpleSelection(anchor, head), options)
    }

// Give beforeSelectionChange handlers a change to influence a
// selection update.
    function filterSelectionChange(doc, sel, options) {
        var obj = {
            ranges: sel.ranges,
            update: function(ranges) {
                var this$1 = this;

                this.ranges = []
                for (var i = 0; i < ranges.length; i++)
                { this$1.ranges[i] = new Range(clipPos(doc, ranges[i].anchor),
                    clipPos(doc, ranges[i].head)) }
            },
            origin: options && options.origin
        }
        signal(doc, "beforeSelectionChange", doc, obj)
        if (doc.cm) { signal(doc.cm, "beforeSelectionChange", doc.cm, obj) }
        if (obj.ranges != sel.ranges) { return normalizeSelection(obj.ranges, obj.ranges.length - 1) }
        else { return sel }
    }

    function setSelectionReplaceHistory(doc, sel, options) {
        var done = doc.history.done, last = lst(done)
        if (last && last.ranges) {
            done[done.length - 1] = sel
            setSelectionNoUndo(doc, sel, options)
        } else {
            setSelection(doc, sel, options)
        }
    }

// Set a new selection.
    function setSelection(doc, sel, options) {
        setSelectionNoUndo(doc, sel, options)
        addSelectionToHistory(doc, doc.sel, doc.cm ? doc.cm.curOp.id : NaN, options)
    }

    function setSelectionNoUndo(doc, sel, options) {
        if (hasHandler(doc, "beforeSelectionChange") || doc.cm && hasHandler(doc.cm, "beforeSelectionChange"))
        { sel = filterSelectionChange(doc, sel, options) }

        var bias = options && options.bias ||
            (cmp(sel.primary().head, doc.sel.primary().head) < 0 ? -1 : 1)
        setSelectionInner(doc, skipAtomicInSelection(doc, sel, bias, true))

        if (!(options && options.scroll === false) && doc.cm)
        { ensureCursorVisible(doc.cm) }
    }

    function setSelectionInner(doc, sel) {
        if (sel.equals(doc.sel)) { return }

        doc.sel = sel

        if (doc.cm) {
            doc.cm.curOp.updateInput = doc.cm.curOp.selectionChanged = true
            signalCursorActivity(doc.cm)
        }
        signalLater(doc, "cursorActivity", doc)
    }

// Verify that the selection does not partially select any atomic
// marked ranges.
    function reCheckSelection(doc) {
        setSelectionInner(doc, skipAtomicInSelection(doc, doc.sel, null, false), sel_dontScroll)
    }

// Return a selection that does not partially select any atomic
// ranges.
    function skipAtomicInSelection(doc, sel, bias, mayClear) {
        var out
        for (var i = 0; i < sel.ranges.length; i++) {
            var range = sel.ranges[i]
            var old = sel.ranges.length == doc.sel.ranges.length && doc.sel.ranges[i]
            var newAnchor = skipAtomic(doc, range.anchor, old && old.anchor, bias, mayClear)
            var newHead = skipAtomic(doc, range.head, old && old.head, bias, mayClear)
            if (out || newAnchor != range.anchor || newHead != range.head) {
                if (!out) { out = sel.ranges.slice(0, i) }
                out[i] = new Range(newAnchor, newHead)
            }
        }
        return out ? normalizeSelection(out, sel.primIndex) : sel
    }

    function skipAtomicInner(doc, pos, oldPos, dir, mayClear) {
        var line = getLine(doc, pos.line)
        if (line.markedSpans) { for (var i = 0; i < line.markedSpans.length; ++i) {
            var sp = line.markedSpans[i], m = sp.marker
            if ((sp.from == null || (m.inclusiveLeft ? sp.from <= pos.ch : sp.from < pos.ch)) &&
                (sp.to == null || (m.inclusiveRight ? sp.to >= pos.ch : sp.to > pos.ch))) {
                if (mayClear) {
                    signal(m, "beforeCursorEnter")
                    if (m.explicitlyCleared) {
                        if (!line.markedSpans) { break }
                        else {--i; continue}
                    }
                }
                if (!m.atomic) { continue }

                if (oldPos) {
                    var near = m.find(dir < 0 ? 1 : -1), diff = void 0
                    if (dir < 0 ? m.inclusiveRight : m.inclusiveLeft)
                    { near = movePos(doc, near, -dir, near && near.line == pos.line ? line : null) }
                    if (near && near.line == pos.line && (diff = cmp(near, oldPos)) && (dir < 0 ? diff < 0 : diff > 0))
                    { return skipAtomicInner(doc, near, pos, dir, mayClear) }
                }

                var far = m.find(dir < 0 ? -1 : 1)
                if (dir < 0 ? m.inclusiveLeft : m.inclusiveRight)
                { far = movePos(doc, far, dir, far.line == pos.line ? line : null) }
                return far ? skipAtomicInner(doc, far, pos, dir, mayClear) : null
            }
        } }
        return pos
    }

// Ensure a given position is not inside an atomic range.
    function skipAtomic(doc, pos, oldPos, bias, mayClear) {
        var dir = bias || 1
        var found = skipAtomicInner(doc, pos, oldPos, dir, mayClear) ||
            (!mayClear && skipAtomicInner(doc, pos, oldPos, dir, true)) ||
            skipAtomicInner(doc, pos, oldPos, -dir, mayClear) ||
            (!mayClear && skipAtomicInner(doc, pos, oldPos, -dir, true))
        if (!found) {
            doc.cantEdit = true
            return Pos(doc.first, 0)
        }
        return found
    }

    function movePos(doc, pos, dir, line) {
        if (dir < 0 && pos.ch == 0) {
            if (pos.line > doc.first) { return clipPos(doc, Pos(pos.line - 1)) }
            else { return null }
        } else if (dir > 0 && pos.ch == (line || getLine(doc, pos.line)).text.length) {
            if (pos.line < doc.first + doc.size - 1) { return Pos(pos.line + 1, 0) }
            else { return null }
        } else {
            return new Pos(pos.line, pos.ch + dir)
        }
    }

    function selectAll(cm) {
        cm.setSelection(Pos(cm.firstLine(), 0), Pos(cm.lastLine()), sel_dontScroll)
    }

// UPDATING

// Allow "beforeChange" event handlers to influence a change
    function filterChange(doc, change, update) {
        var obj = {
            canceled: false,
            from: change.from,
            to: change.to,
            text: change.text,
            origin: change.origin,
            cancel: function () { return obj.canceled = true; }
        }
        if (update) { obj.update = function (from, to, text, origin) {
            if (from) { obj.from = clipPos(doc, from) }
            if (to) { obj.to = clipPos(doc, to) }
            if (text) { obj.text = text }
            if (origin !== undefined) { obj.origin = origin }
        } }
        signal(doc, "beforeChange", doc, obj)
        if (doc.cm) { signal(doc.cm, "beforeChange", doc.cm, obj) }

        if (obj.canceled) { return null }
        return {from: obj.from, to: obj.to, text: obj.text, origin: obj.origin}
    }

// Apply a change to a document, and add it to the document's
// history, and propagating it to all linked documents.
    function makeChange(doc, change, ignoreReadOnly) {
        if (doc.cm) {
            if (!doc.cm.curOp) { return operation(doc.cm, makeChange)(doc, change, ignoreReadOnly) }
            if (doc.cm.state.suppressEdits) { return }
        }

        if (hasHandler(doc, "beforeChange") || doc.cm && hasHandler(doc.cm, "beforeChange")) {
            change = filterChange(doc, change, true)
            if (!change) { return }
        }

        // Possibly split or suppress the update based on the presence
        // of read-only spans in its range.
        var split = sawReadOnlySpans && !ignoreReadOnly && removeReadOnlyRanges(doc, change.from, change.to)
        if (split) {
            for (var i = split.length - 1; i >= 0; --i)
            { makeChangeInner(doc, {from: split[i].from, to: split[i].to, text: i ? [""] : change.text}) }
        } else {
            makeChangeInner(doc, change)
        }
    }

    function makeChangeInner(doc, change) {
        if (change.text.length == 1 && change.text[0] == "" && cmp(change.from, change.to) == 0) { return }
        var selAfter = computeSelAfterChange(doc, change)
        addChangeToHistory(doc, change, selAfter, doc.cm ? doc.cm.curOp.id : NaN)

        makeChangeSingleDoc(doc, change, selAfter, stretchSpansOverChange(doc, change))
        var rebased = []

        linkedDocs(doc, function (doc, sharedHist) {
            if (!sharedHist && indexOf(rebased, doc.history) == -1) {
                rebaseHist(doc.history, change)
                rebased.push(doc.history)
            }
            makeChangeSingleDoc(doc, change, null, stretchSpansOverChange(doc, change))
        })
    }

// Revert a change stored in a document's history.
    function makeChangeFromHistory(doc, type, allowSelectionOnly) {
        if (doc.cm && doc.cm.state.suppressEdits && !allowSelectionOnly) { return }

        var hist = doc.history, event, selAfter = doc.sel
        var source = type == "undo" ? hist.done : hist.undone, dest = type == "undo" ? hist.undone : hist.done

        // Verify that there is a useable event (so that ctrl-z won't
        // needlessly clear selection events)
        var i = 0
        for (; i < source.length; i++) {
            event = source[i]
            if (allowSelectionOnly ? event.ranges && !event.equals(doc.sel) : !event.ranges)
            { break }
        }
        if (i == source.length) { return }
        hist.lastOrigin = hist.lastSelOrigin = null

        for (;;) {
            event = source.pop()
            if (event.ranges) {
                pushSelectionToHistory(event, dest)
                if (allowSelectionOnly && !event.equals(doc.sel)) {
                    setSelection(doc, event, {clearRedo: false})
                    return
                }
                selAfter = event
            }
            else { break }
        }

        // Build up a reverse change object to add to the opposite history
        // stack (redo when undoing, and vice versa).
        var antiChanges = []
        pushSelectionToHistory(selAfter, dest)
        dest.push({changes: antiChanges, generation: hist.generation})
        hist.generation = event.generation || ++hist.maxGeneration

        var filter = hasHandler(doc, "beforeChange") || doc.cm && hasHandler(doc.cm, "beforeChange")

        var loop = function ( i ) {
            var change = event.changes[i]
            change.origin = type
            if (filter && !filterChange(doc, change, false)) {
                source.length = 0
                return {}
            }

            antiChanges.push(historyChangeFromChange(doc, change))

            var after = i ? computeSelAfterChange(doc, change) : lst(source)
            makeChangeSingleDoc(doc, change, after, mergeOldSpans(doc, change))
            if (!i && doc.cm) { doc.cm.scrollIntoView({from: change.from, to: changeEnd(change)}) }
            var rebased = []

            // Propagate to the linked documents
            linkedDocs(doc, function (doc, sharedHist) {
                if (!sharedHist && indexOf(rebased, doc.history) == -1) {
                    rebaseHist(doc.history, change)
                    rebased.push(doc.history)
                }
                makeChangeSingleDoc(doc, change, null, mergeOldSpans(doc, change))
            })
        };

        for (var i$1 = event.changes.length - 1; i$1 >= 0; --i$1) {
            var returned = loop( i$1 );

            if ( returned ) return returned.v;
        }
    }

// Sub-views need their line numbers shifted when text is added
// above or below them in the parent document.
    function shiftDoc(doc, distance) {
        if (distance == 0) { return }
        doc.first += distance
        doc.sel = new Selection(map(doc.sel.ranges, function (range) { return new Range(
            Pos(range.anchor.line + distance, range.anchor.ch),
            Pos(range.head.line + distance, range.head.ch)
        ); }), doc.sel.primIndex)
        if (doc.cm) {
            regChange(doc.cm, doc.first, doc.first - distance, distance)
            for (var d = doc.cm.display, l = d.viewFrom; l < d.viewTo; l++)
            { regLineChange(doc.cm, l, "gutter") }
        }
    }

// More lower-level change function, handling only a single document
// (not linked ones).
    function makeChangeSingleDoc(doc, change, selAfter, spans) {
        if (doc.cm && !doc.cm.curOp)
        { return operation(doc.cm, makeChangeSingleDoc)(doc, change, selAfter, spans) }

        if (change.to.line < doc.first) {
            shiftDoc(doc, change.text.length - 1 - (change.to.line - change.from.line))
            return
        }
        if (change.from.line > doc.lastLine()) { return }

        // Clip the change to the size of this doc
        if (change.from.line < doc.first) {
            var shift = change.text.length - 1 - (doc.first - change.from.line)
            shiftDoc(doc, shift)
            change = {from: Pos(doc.first, 0), to: Pos(change.to.line + shift, change.to.ch),
                text: [lst(change.text)], origin: change.origin}
        }
        var last = doc.lastLine()
        if (change.to.line > last) {
            change = {from: change.from, to: Pos(last, getLine(doc, last).text.length),
                text: [change.text[0]], origin: change.origin}
        }

        change.removed = getBetween(doc, change.from, change.to)

        if (!selAfter) { selAfter = computeSelAfterChange(doc, change) }
        if (doc.cm) { makeChangeSingleDocInEditor(doc.cm, change, spans) }
        else { updateDoc(doc, change, spans) }
        setSelectionNoUndo(doc, selAfter, sel_dontScroll)
    }

// Handle the interaction of a change to a document with the editor
// that this document is part of.
    function makeChangeSingleDocInEditor(cm, change, spans) {
        var doc = cm.doc, display = cm.display, from = change.from, to = change.to

        var recomputeMaxLength = false, checkWidthStart = from.line
        if (!cm.options.lineWrapping) {
            checkWidthStart = lineNo(visualLine(getLine(doc, from.line)))
            doc.iter(checkWidthStart, to.line + 1, function (line) {
                if (line == display.maxLine) {
                    recomputeMaxLength = true
                    return true
                }
            })
        }

        if (doc.sel.contains(change.from, change.to) > -1)
        { signalCursorActivity(cm) }

        updateDoc(doc, change, spans, estimateHeight(cm))

        if (!cm.options.lineWrapping) {
            doc.iter(checkWidthStart, from.line + change.text.length, function (line) {
                var len = lineLength(line)
                if (len > display.maxLineLength) {
                    display.maxLine = line
                    display.maxLineLength = len
                    display.maxLineChanged = true
                    recomputeMaxLength = false
                }
            })
            if (recomputeMaxLength) { cm.curOp.updateMaxLine = true }
        }

        // Adjust frontier, schedule worker
        doc.frontier = Math.min(doc.frontier, from.line)
        startWorker(cm, 400)

        var lendiff = change.text.length - (to.line - from.line) - 1
        // Remember that these lines changed, for updating the display
        if (change.full)
        { regChange(cm) }
        else if (from.line == to.line && change.text.length == 1 && !isWholeLineUpdate(cm.doc, change))
        { regLineChange(cm, from.line, "text") }
        else
        { regChange(cm, from.line, to.line + 1, lendiff) }

        var changesHandler = hasHandler(cm, "changes"), changeHandler = hasHandler(cm, "change")
        if (changeHandler || changesHandler) {
            var obj = {
                from: from, to: to,
                text: change.text,
                removed: change.removed,
                origin: change.origin
            }
            if (changeHandler) { signalLater(cm, "change", cm, obj) }
            if (changesHandler) { (cm.curOp.changeObjs || (cm.curOp.changeObjs = [])).push(obj) }
        }
        cm.display.selForContextMenu = null
    }

    function replaceRange(doc, code, from, to, origin) {
        if (!to) { to = from }
        if (cmp(to, from) < 0) { var tmp = to; to = from; from = tmp }
        if (typeof code == "string") { code = doc.splitLines(code) }
        makeChange(doc, {from: from, to: to, text: code, origin: origin})
    }

// Rebasing/resetting history to deal with externally-sourced changes

    function rebaseHistSelSingle(pos, from, to, diff) {
        if (to < pos.line) {
            pos.line += diff
        } else if (from < pos.line) {
            pos.line = from
            pos.ch = 0
        }
    }

// Tries to rebase an array of history events given a change in the
// document. If the change touches the same lines as the event, the
// event, and everything 'behind' it, is discarded. If the change is
// before the event, the event's positions are updated. Uses a
// copy-on-write scheme for the positions, to avoid having to
// reallocate them all on every rebase, but also avoid problems with
// shared position objects being unsafely updated.
    function rebaseHistArray(array, from, to, diff) {
        for (var i = 0; i < array.length; ++i) {
            var sub = array[i], ok = true
            if (sub.ranges) {
                if (!sub.copied) { sub = array[i] = sub.deepCopy(); sub.copied = true }
                for (var j = 0; j < sub.ranges.length; j++) {
                    rebaseHistSelSingle(sub.ranges[j].anchor, from, to, diff)
                    rebaseHistSelSingle(sub.ranges[j].head, from, to, diff)
                }
                continue
            }
            for (var j$1 = 0; j$1 < sub.changes.length; ++j$1) {
                var cur = sub.changes[j$1]
                if (to < cur.from.line) {
                    cur.from = Pos(cur.from.line + diff, cur.from.ch)
                    cur.to = Pos(cur.to.line + diff, cur.to.ch)
                } else if (from <= cur.to.line) {
                    ok = false
                    break
                }
            }
            if (!ok) {
                array.splice(0, i + 1)
                i = 0
            }
        }
    }

    function rebaseHist(hist, change) {
        var from = change.from.line, to = change.to.line, diff = change.text.length - (to - from) - 1
        rebaseHistArray(hist.done, from, to, diff)
        rebaseHistArray(hist.undone, from, to, diff)
    }

// Utility for applying a change to a line by handle or number,
// returning the number and optionally registering the line as
// changed.
    function changeLine(doc, handle, changeType, op) {
        var no = handle, line = handle
        if (typeof handle == "number") { line = getLine(doc, clipLine(doc, handle)) }
        else { no = lineNo(handle) }
        if (no == null) { return null }
        if (op(line, no) && doc.cm) { regLineChange(doc.cm, no, changeType) }
        return line
    }

// The document is represented as a BTree consisting of leaves, with
// chunk of lines in them, and branches, with up to ten leaves or
// other branch nodes below them. The top node is always a branch
// node, and is the document object itself (meaning it has
// additional methods and properties).
//
// All nodes have parent links. The tree is used both to go from
// line numbers to line objects, and to go from objects to numbers.
// It also indexes by height, and is used to convert between height
// and line object, and to find the total height of the document.
//
// See also http://marijnhaverbeke.nl/blog/codemirror-line-tree.html

    function LeafChunk(lines) {
        var this$1 = this;

        this.lines = lines
        this.parent = null
        var height = 0
        for (var i = 0; i < lines.length; ++i) {
            lines[i].parent = this$1
            height += lines[i].height
        }
        this.height = height
    }

    LeafChunk.prototype = {
        chunkSize: function() { return this.lines.length },
        // Remove the n lines at offset 'at'.
        removeInner: function(at, n) {
            var this$1 = this;

            for (var i = at, e = at + n; i < e; ++i) {
                var line = this$1.lines[i]
                this$1.height -= line.height
                cleanUpLine(line)
                signalLater(line, "delete")
            }
            this.lines.splice(at, n)
        },
        // Helper used to collapse a small branch into a single leaf.
        collapse: function(lines) {
            lines.push.apply(lines, this.lines)
        },
        // Insert the given array of lines at offset 'at', count them as
        // having the given height.
        insertInner: function(at, lines, height) {
            var this$1 = this;

            this.height += height
            this.lines = this.lines.slice(0, at).concat(lines).concat(this.lines.slice(at))
            for (var i = 0; i < lines.length; ++i) { lines[i].parent = this$1 }
        },
        // Used to iterate over a part of the tree.
        iterN: function(at, n, op) {
            var this$1 = this;

            for (var e = at + n; at < e; ++at)
            { if (op(this$1.lines[at])) { return true } }
        }
    }

    function BranchChunk(children) {
        var this$1 = this;

        this.children = children
        var size = 0, height = 0
        for (var i = 0; i < children.length; ++i) {
            var ch = children[i]
            size += ch.chunkSize(); height += ch.height
            ch.parent = this$1
        }
        this.size = size
        this.height = height
        this.parent = null
    }

    BranchChunk.prototype = {
        chunkSize: function() { return this.size },
        removeInner: function(at, n) {
            var this$1 = this;

            this.size -= n
            for (var i = 0; i < this.children.length; ++i) {
                var child = this$1.children[i], sz = child.chunkSize()
                if (at < sz) {
                    var rm = Math.min(n, sz - at), oldHeight = child.height
                    child.removeInner(at, rm)
                    this$1.height -= oldHeight - child.height
                    if (sz == rm) { this$1.children.splice(i--, 1); child.parent = null }
                    if ((n -= rm) == 0) { break }
                    at = 0
                } else { at -= sz }
            }
            // If the result is smaller than 25 lines, ensure that it is a
            // single leaf node.
            if (this.size - n < 25 &&
                (this.children.length > 1 || !(this.children[0] instanceof LeafChunk))) {
                var lines = []
                this.collapse(lines)
                this.children = [new LeafChunk(lines)]
                this.children[0].parent = this
            }
        },
        collapse: function(lines) {
            var this$1 = this;

            for (var i = 0; i < this.children.length; ++i) { this$1.children[i].collapse(lines) }
        },
        insertInner: function(at, lines, height) {
            var this$1 = this;

            this.size += lines.length
            this.height += height
            for (var i = 0; i < this.children.length; ++i) {
                var child = this$1.children[i], sz = child.chunkSize()
                if (at <= sz) {
                    child.insertInner(at, lines, height)
                    if (child.lines && child.lines.length > 50) {
                        // To avoid memory thrashing when child.lines is huge (e.g. first view of a large file), it's never spliced.
                        // Instead, small slices are taken. They're taken in order because sequential memory accesses are fastest.
                        var remaining = child.lines.length % 25 + 25
                        for (var pos = remaining; pos < child.lines.length;) {
                            var leaf = new LeafChunk(child.lines.slice(pos, pos += 25))
                            child.height -= leaf.height
                            this$1.children.splice(++i, 0, leaf)
                            leaf.parent = this$1
                        }
                        child.lines = child.lines.slice(0, remaining)
                        this$1.maybeSpill()
                    }
                    break
                }
                at -= sz
            }
        },
        // When a node has grown, check whether it should be split.
        maybeSpill: function() {
            if (this.children.length <= 10) { return }
            var me = this
            do {
                var spilled = me.children.splice(me.children.length - 5, 5)
                var sibling = new BranchChunk(spilled)
                if (!me.parent) { // Become the parent node
                    var copy = new BranchChunk(me.children)
                    copy.parent = me
                    me.children = [copy, sibling]
                    me = copy
                } else {
                    me.size -= sibling.size
                    me.height -= sibling.height
                    var myIndex = indexOf(me.parent.children, me)
                    me.parent.children.splice(myIndex + 1, 0, sibling)
                }
                sibling.parent = me.parent
            } while (me.children.length > 10)
            me.parent.maybeSpill()
        },
        iterN: function(at, n, op) {
            var this$1 = this;

            for (var i = 0; i < this.children.length; ++i) {
                var child = this$1.children[i], sz = child.chunkSize()
                if (at < sz) {
                    var used = Math.min(n, sz - at)
                    if (child.iterN(at, used, op)) { return true }
                    if ((n -= used) == 0) { break }
                    at = 0
                } else { at -= sz }
            }
        }
    }

// Line widgets are block elements displayed above or below a line.

    function LineWidget(doc, node, options) {
        var this$1 = this;

        if (options) { for (var opt in options) { if (options.hasOwnProperty(opt))
        { this$1[opt] = options[opt] } } }
        this.doc = doc
        this.node = node
    }
    eventMixin(LineWidget)

    function adjustScrollWhenAboveVisible(cm, line, diff) {
        if (heightAtLine(line) < ((cm.curOp && cm.curOp.scrollTop) || cm.doc.scrollTop))
        { addToScrollPos(cm, null, diff) }
    }

    LineWidget.prototype.clear = function() {
        var this$1 = this;

        var cm = this.doc.cm, ws = this.line.widgets, line = this.line, no = lineNo(line)
        if (no == null || !ws) { return }
        for (var i = 0; i < ws.length; ++i) { if (ws[i] == this$1) { ws.splice(i--, 1) } }
        if (!ws.length) { line.widgets = null }
        var height = widgetHeight(this)
        updateLineHeight(line, Math.max(0, line.height - height))
        if (cm) { runInOp(cm, function () {
            adjustScrollWhenAboveVisible(cm, line, -height)
            regLineChange(cm, no, "widget")
        }) }
    }
    LineWidget.prototype.changed = function() {
        var oldH = this.height, cm = this.doc.cm, line = this.line
        this.height = null
        var diff = widgetHeight(this) - oldH
        if (!diff) { return }
        updateLineHeight(line, line.height + diff)
        if (cm) { runInOp(cm, function () {
            cm.curOp.forceUpdate = true
            adjustScrollWhenAboveVisible(cm, line, diff)
        }) }
    }

    function addLineWidget(doc, handle, node, options) {
        var widget = new LineWidget(doc, node, options)
        var cm = doc.cm
        if (cm && widget.noHScroll) { cm.display.alignWidgets = true }
        changeLine(doc, handle, "widget", function (line) {
            var widgets = line.widgets || (line.widgets = [])
            if (widget.insertAt == null) { widgets.push(widget) }
            else { widgets.splice(Math.min(widgets.length - 1, Math.max(0, widget.insertAt)), 0, widget) }
            widget.line = line
            if (cm && !lineIsHidden(doc, line)) {
                var aboveVisible = heightAtLine(line) < doc.scrollTop
                updateLineHeight(line, line.height + widgetHeight(widget))
                if (aboveVisible) { addToScrollPos(cm, null, widget.height) }
                cm.curOp.forceUpdate = true
            }
            return true
        })
        return widget
    }

// TEXTMARKERS

// Created with markText and setBookmark methods. A TextMarker is a
// handle that can be used to clear or find a marked position in the
// document. Line objects hold arrays (markedSpans) containing
// {from, to, marker} object pointing to such marker objects, and
// indicating that such a marker is present on that line. Multiple
// lines may point to the same marker when it spans across lines.
// The spans will have null for their from/to properties when the
// marker continues beyond the start/end of the line. Markers have
// links back to the lines they currently touch.

// Collapsed markers have unique ids, in order to be able to order
// them, which is needed for uniquely determining an outer marker
// when they overlap (they may nest, but not partially overlap).
    var nextMarkerId = 0

    function TextMarker(doc, type) {
        this.lines = []
        this.type = type
        this.doc = doc
        this.id = ++nextMarkerId
    }
    eventMixin(TextMarker)

// Clear the marker.
    TextMarker.prototype.clear = function() {
        var this$1 = this;

        if (this.explicitlyCleared) { return }
        var cm = this.doc.cm, withOp = cm && !cm.curOp
        if (withOp) { startOperation(cm) }
        if (hasHandler(this, "clear")) {
            var found = this.find()
            if (found) { signalLater(this, "clear", found.from, found.to) }
        }
        var min = null, max = null
        for (var i = 0; i < this.lines.length; ++i) {
            var line = this$1.lines[i]
            var span = getMarkedSpanFor(line.markedSpans, this$1)
            if (cm && !this$1.collapsed) { regLineChange(cm, lineNo(line), "text") }
            else if (cm) {
                if (span.to != null) { max = lineNo(line) }
                if (span.from != null) { min = lineNo(line) }
            }
            line.markedSpans = removeMarkedSpan(line.markedSpans, span)
            if (span.from == null && this$1.collapsed && !lineIsHidden(this$1.doc, line) && cm)
            { updateLineHeight(line, textHeight(cm.display)) }
        }
        if (cm && this.collapsed && !cm.options.lineWrapping) { for (var i$1 = 0; i$1 < this.lines.length; ++i$1) {
            var visual = visualLine(this$1.lines[i$1]), len = lineLength(visual)
            if (len > cm.display.maxLineLength) {
                cm.display.maxLine = visual
                cm.display.maxLineLength = len
                cm.display.maxLineChanged = true
            }
        } }

        if (min != null && cm && this.collapsed) { regChange(cm, min, max + 1) }
        this.lines.length = 0
        this.explicitlyCleared = true
        if (this.atomic && this.doc.cantEdit) {
            this.doc.cantEdit = false
            if (cm) { reCheckSelection(cm.doc) }
        }
        if (cm) { signalLater(cm, "markerCleared", cm, this) }
        if (withOp) { endOperation(cm) }
        if (this.parent) { this.parent.clear() }
    }

// Find the position of the marker in the document. Returns a {from,
// to} object by default. Side can be passed to get a specific side
// -- 0 (both), -1 (left), or 1 (right). When lineObj is true, the
// Pos objects returned contain a line object, rather than a line
// number (used to prevent looking up the same line twice).
    TextMarker.prototype.find = function(side, lineObj) {
        var this$1 = this;

        if (side == null && this.type == "bookmark") { side = 1 }
        var from, to
        for (var i = 0; i < this.lines.length; ++i) {
            var line = this$1.lines[i]
            var span = getMarkedSpanFor(line.markedSpans, this$1)
            if (span.from != null) {
                from = Pos(lineObj ? line : lineNo(line), span.from)
                if (side == -1) { return from }
            }
            if (span.to != null) {
                to = Pos(lineObj ? line : lineNo(line), span.to)
                if (side == 1) { return to }
            }
        }
        return from && {from: from, to: to}
    }

// Signals that the marker's widget changed, and surrounding layout
// should be recomputed.
    TextMarker.prototype.changed = function() {
        var pos = this.find(-1, true), widget = this, cm = this.doc.cm
        if (!pos || !cm) { return }
        runInOp(cm, function () {
            var line = pos.line, lineN = lineNo(pos.line)
            var view = findViewForLine(cm, lineN)
            if (view) {
                clearLineMeasurementCacheFor(view)
                cm.curOp.selectionChanged = cm.curOp.forceUpdate = true
            }
            cm.curOp.updateMaxLine = true
            if (!lineIsHidden(widget.doc, line) && widget.height != null) {
                var oldHeight = widget.height
                widget.height = null
                var dHeight = widgetHeight(widget) - oldHeight
                if (dHeight)
                { updateLineHeight(line, line.height + dHeight) }
            }
        })
    }

    TextMarker.prototype.attachLine = function(line) {
        if (!this.lines.length && this.doc.cm) {
            var op = this.doc.cm.curOp
            if (!op.maybeHiddenMarkers || indexOf(op.maybeHiddenMarkers, this) == -1)
            { (op.maybeUnhiddenMarkers || (op.maybeUnhiddenMarkers = [])).push(this) }
        }
        this.lines.push(line)
    }
    TextMarker.prototype.detachLine = function(line) {
        this.lines.splice(indexOf(this.lines, line), 1)
        if (!this.lines.length && this.doc.cm) {
            var op = this.doc.cm.curOp;(op.maybeHiddenMarkers || (op.maybeHiddenMarkers = [])).push(this)
        }
    }

// Create a marker, wire it up to the right lines, and
    function markText(doc, from, to, options, type) {
        // Shared markers (across linked documents) are handled separately
        // (markTextShared will call out to this again, once per
        // document).
        if (options && options.shared) { return markTextShared(doc, from, to, options, type) }
        // Ensure we are in an operation.
        if (doc.cm && !doc.cm.curOp) { return operation(doc.cm, markText)(doc, from, to, options, type) }

        var marker = new TextMarker(doc, type), diff = cmp(from, to)
        if (options) { copyObj(options, marker, false) }
        // Don't connect empty markers unless clearWhenEmpty is false
        if (diff > 0 || diff == 0 && marker.clearWhenEmpty !== false)
        { return marker }
        if (marker.replacedWith) {
            // Showing up as a widget implies collapsed (widget replaces text)
            marker.collapsed = true
            marker.widgetNode = elt("span", [marker.replacedWith], "CodeMirror-widget")
            if (!options.handleMouseEvents) { marker.widgetNode.setAttribute("cm-ignore-events", "true") }
            if (options.insertLeft) { marker.widgetNode.insertLeft = true }
        }
        if (marker.collapsed) {
            if (conflictingCollapsedRange(doc, from.line, from, to, marker) ||
                from.line != to.line && conflictingCollapsedRange(doc, to.line, from, to, marker))
            { throw new Error("Inserting collapsed marker partially overlapping an existing one") }
            seeCollapsedSpans()
        }

        if (marker.addToHistory)
        { addChangeToHistory(doc, {from: from, to: to, origin: "markText"}, doc.sel, NaN) }

        var curLine = from.line, cm = doc.cm, updateMaxLine
        doc.iter(curLine, to.line + 1, function (line) {
            if (cm && marker.collapsed && !cm.options.lineWrapping && visualLine(line) == cm.display.maxLine)
            { updateMaxLine = true }
            if (marker.collapsed && curLine != from.line) { updateLineHeight(line, 0) }
            addMarkedSpan(line, new MarkedSpan(marker,
                curLine == from.line ? from.ch : null,
                curLine == to.line ? to.ch : null))
            ++curLine
        })
        // lineIsHidden depends on the presence of the spans, so needs a second pass
        if (marker.collapsed) { doc.iter(from.line, to.line + 1, function (line) {
            if (lineIsHidden(doc, line)) { updateLineHeight(line, 0) }
        }) }

        if (marker.clearOnEnter) { on(marker, "beforeCursorEnter", function () { return marker.clear(); }) }

        if (marker.readOnly) {
            seeReadOnlySpans()
            if (doc.history.done.length || doc.history.undone.length)
            { doc.clearHistory() }
        }
        if (marker.collapsed) {
            marker.id = ++nextMarkerId
            marker.atomic = true
        }
        if (cm) {
            // Sync editor state
            if (updateMaxLine) { cm.curOp.updateMaxLine = true }
            if (marker.collapsed)
            { regChange(cm, from.line, to.line + 1) }
            else if (marker.className || marker.title || marker.startStyle || marker.endStyle || marker.css)
            { for (var i = from.line; i <= to.line; i++) { regLineChange(cm, i, "text") } }
            if (marker.atomic) { reCheckSelection(cm.doc) }
            signalLater(cm, "markerAdded", cm, marker)
        }
        return marker
    }

// SHARED TEXTMARKERS

// A shared marker spans multiple linked documents. It is
// implemented as a meta-marker-object controlling multiple normal
// markers.
    function SharedTextMarker(markers, primary) {
        var this$1 = this;

        this.markers = markers
        this.primary = primary
        for (var i = 0; i < markers.length; ++i)
        { markers[i].parent = this$1 }
    }
    eventMixin(SharedTextMarker)

    SharedTextMarker.prototype.clear = function() {
        var this$1 = this;

        if (this.explicitlyCleared) { return }
        this.explicitlyCleared = true
        for (var i = 0; i < this.markers.length; ++i)
        { this$1.markers[i].clear() }
        signalLater(this, "clear")
    }
    SharedTextMarker.prototype.find = function(side, lineObj) {
        return this.primary.find(side, lineObj)
    }

    function markTextShared(doc, from, to, options, type) {
        options = copyObj(options)
        options.shared = false
        var markers = [markText(doc, from, to, options, type)], primary = markers[0]
        var widget = options.widgetNode
        linkedDocs(doc, function (doc) {
            if (widget) { options.widgetNode = widget.cloneNode(true) }
            markers.push(markText(doc, clipPos(doc, from), clipPos(doc, to), options, type))
            for (var i = 0; i < doc.linked.length; ++i)
            { if (doc.linked[i].isParent) { return } }
            primary = lst(markers)
        })
        return new SharedTextMarker(markers, primary)
    }

    function findSharedMarkers(doc) {
        return doc.findMarks(Pos(doc.first, 0), doc.clipPos(Pos(doc.lastLine())), function (m) { return m.parent; })
    }

    function copySharedMarkers(doc, markers) {
        for (var i = 0; i < markers.length; i++) {
            var marker = markers[i], pos = marker.find()
            var mFrom = doc.clipPos(pos.from), mTo = doc.clipPos(pos.to)
            if (cmp(mFrom, mTo)) {
                var subMark = markText(doc, mFrom, mTo, marker.primary, marker.primary.type)
                marker.markers.push(subMark)
                subMark.parent = marker
            }
        }
    }

    function detachSharedMarkers(markers) {
        var loop = function ( i ) {
            var marker = markers[i], linked = [marker.primary.doc]
            linkedDocs(marker.primary.doc, function (d) { return linked.push(d); })
            for (var j = 0; j < marker.markers.length; j++) {
                var subMarker = marker.markers[j]
                if (indexOf(linked, subMarker.doc) == -1) {
                    subMarker.parent = null
                    marker.markers.splice(j--, 1)
                }
            }
        };

        for (var i = 0; i < markers.length; i++) loop( i );
    }

    var nextDocId = 0
    var Doc = function(text, mode, firstLine, lineSep) {
        if (!(this instanceof Doc)) { return new Doc(text, mode, firstLine, lineSep) }
        if (firstLine == null) { firstLine = 0 }

        BranchChunk.call(this, [new LeafChunk([new Line("", null)])])
        this.first = firstLine
        this.scrollTop = this.scrollLeft = 0
        this.cantEdit = false
        this.cleanGeneration = 1
        this.frontier = firstLine
        var start = Pos(firstLine, 0)
        this.sel = simpleSelection(start)
        this.history = new History(null)
        this.id = ++nextDocId
        this.modeOption = mode
        this.lineSep = lineSep
        this.extend = false

        if (typeof text == "string") { text = this.splitLines(text) }
        updateDoc(this, {from: start, to: start, text: text})
        setSelection(this, simpleSelection(start), sel_dontScroll)
    }

    Doc.prototype = createObj(BranchChunk.prototype, {
        constructor: Doc,
        // Iterate over the document. Supports two forms -- with only one
        // argument, it calls that for each line in the document. With
        // three, it iterates over the range given by the first two (with
        // the second being non-inclusive).
        iter: function(from, to, op) {
            if (op) { this.iterN(from - this.first, to - from, op) }
            else { this.iterN(this.first, this.first + this.size, from) }
        },

        // Non-public interface for adding and removing lines.
        insert: function(at, lines) {
            var height = 0
            for (var i = 0; i < lines.length; ++i) { height += lines[i].height }
            this.insertInner(at - this.first, lines, height)
        },
        remove: function(at, n) { this.removeInner(at - this.first, n) },

        // From here, the methods are part of the public interface. Most
        // are also available from CodeMirror (editor) instances.

        getValue: function(lineSep) {
            var lines = getLines(this, this.first, this.first + this.size)
            if (lineSep === false) { return lines }
            return lines.join(lineSep || this.lineSeparator())
        },
        setValue: docMethodOp(function(code) {
            var top = Pos(this.first, 0), last = this.first + this.size - 1
            makeChange(this, {from: top, to: Pos(last, getLine(this, last).text.length),
                text: this.splitLines(code), origin: "setValue", full: true}, true)
            setSelection(this, simpleSelection(top))
        }),
        replaceRange: function(code, from, to, origin) {
            from = clipPos(this, from)
            to = to ? clipPos(this, to) : from
            replaceRange(this, code, from, to, origin)
        },
        getRange: function(from, to, lineSep) {
            var lines = getBetween(this, clipPos(this, from), clipPos(this, to))
            if (lineSep === false) { return lines }
            return lines.join(lineSep || this.lineSeparator())
        },

        getLine: function(line) {var l = this.getLineHandle(line); return l && l.text},

        getLineHandle: function(line) {if (isLine(this, line)) { return getLine(this, line) }},
        getLineNumber: function(line) {return lineNo(line)},

        getLineHandleVisualStart: function(line) {
            if (typeof line == "number") { line = getLine(this, line) }
            return visualLine(line)
        },

        lineCount: function() {return this.size},
        firstLine: function() {return this.first},
        lastLine: function() {return this.first + this.size - 1},

        clipPos: function(pos) {return clipPos(this, pos)},

        getCursor: function(start) {
            var range$$1 = this.sel.primary(), pos
            if (start == null || start == "head") { pos = range$$1.head }
            else if (start == "anchor") { pos = range$$1.anchor }
            else if (start == "end" || start == "to" || start === false) { pos = range$$1.to() }
            else { pos = range$$1.from() }
            return pos
        },
        listSelections: function() { return this.sel.ranges },
        somethingSelected: function() {return this.sel.somethingSelected()},

        setCursor: docMethodOp(function(line, ch, options) {
            setSimpleSelection(this, clipPos(this, typeof line == "number" ? Pos(line, ch || 0) : line), null, options)
        }),
        setSelection: docMethodOp(function(anchor, head, options) {
            setSimpleSelection(this, clipPos(this, anchor), clipPos(this, head || anchor), options)
        }),
        extendSelection: docMethodOp(function(head, other, options) {
            extendSelection(this, clipPos(this, head), other && clipPos(this, other), options)
        }),
        extendSelections: docMethodOp(function(heads, options) {
            extendSelections(this, clipPosArray(this, heads), options)
        }),
        extendSelectionsBy: docMethodOp(function(f, options) {
            var heads = map(this.sel.ranges, f)
            extendSelections(this, clipPosArray(this, heads), options)
        }),
        setSelections: docMethodOp(function(ranges, primary, options) {
            var this$1 = this;

            if (!ranges.length) { return }
            var out = []
            for (var i = 0; i < ranges.length; i++)
            { out[i] = new Range(clipPos(this$1, ranges[i].anchor),
                clipPos(this$1, ranges[i].head)) }
            if (primary == null) { primary = Math.min(ranges.length - 1, this.sel.primIndex) }
            setSelection(this, normalizeSelection(out, primary), options)
        }),
        addSelection: docMethodOp(function(anchor, head, options) {
            var ranges = this.sel.ranges.slice(0)
            ranges.push(new Range(clipPos(this, anchor), clipPos(this, head || anchor)))
            setSelection(this, normalizeSelection(ranges, ranges.length - 1), options)
        }),

        getSelection: function(lineSep) {
            var this$1 = this;

            var ranges = this.sel.ranges, lines
            for (var i = 0; i < ranges.length; i++) {
                var sel = getBetween(this$1, ranges[i].from(), ranges[i].to())
                lines = lines ? lines.concat(sel) : sel
            }
            if (lineSep === false) { return lines }
            else { return lines.join(lineSep || this.lineSeparator()) }
        },
        getSelections: function(lineSep) {
            var this$1 = this;

            var parts = [], ranges = this.sel.ranges
            for (var i = 0; i < ranges.length; i++) {
                var sel = getBetween(this$1, ranges[i].from(), ranges[i].to())
                if (lineSep !== false) { sel = sel.join(lineSep || this$1.lineSeparator()) }
                parts[i] = sel
            }
            return parts
        },
        replaceSelection: function(code, collapse, origin) {
            var dup = []
            for (var i = 0; i < this.sel.ranges.length; i++)
            { dup[i] = code }
            this.replaceSelections(dup, collapse, origin || "+input")
        },
        replaceSelections: docMethodOp(function(code, collapse, origin) {
            var this$1 = this;

            var changes = [], sel = this.sel
            for (var i = 0; i < sel.ranges.length; i++) {
                var range$$1 = sel.ranges[i]
                changes[i] = {from: range$$1.from(), to: range$$1.to(), text: this$1.splitLines(code[i]), origin: origin}
            }
            var newSel = collapse && collapse != "end" && computeReplacedSel(this, changes, collapse)
            for (var i$1 = changes.length - 1; i$1 >= 0; i$1--)
            { makeChange(this$1, changes[i$1]) }
            if (newSel) { setSelectionReplaceHistory(this, newSel) }
            else if (this.cm) { ensureCursorVisible(this.cm) }
        }),
        undo: docMethodOp(function() {makeChangeFromHistory(this, "undo")}),
        redo: docMethodOp(function() {makeChangeFromHistory(this, "redo")}),
        undoSelection: docMethodOp(function() {makeChangeFromHistory(this, "undo", true)}),
        redoSelection: docMethodOp(function() {makeChangeFromHistory(this, "redo", true)}),

        setExtending: function(val) {this.extend = val},
        getExtending: function() {return this.extend},

        historySize: function() {
            var hist = this.history, done = 0, undone = 0
            for (var i = 0; i < hist.done.length; i++) { if (!hist.done[i].ranges) { ++done } }
            for (var i$1 = 0; i$1 < hist.undone.length; i$1++) { if (!hist.undone[i$1].ranges) { ++undone } }
            return {undo: done, redo: undone}
        },
        clearHistory: function() {this.history = new History(this.history.maxGeneration)},

        markClean: function() {
            this.cleanGeneration = this.changeGeneration(true)
        },
        changeGeneration: function(forceSplit) {
            if (forceSplit)
            { this.history.lastOp = this.history.lastSelOp = this.history.lastOrigin = null }
            return this.history.generation
        },
        isClean: function (gen) {
            return this.history.generation == (gen || this.cleanGeneration)
        },

        getHistory: function() {
            return {done: copyHistoryArray(this.history.done),
                undone: copyHistoryArray(this.history.undone)}
        },
        setHistory: function(histData) {
            var hist = this.history = new History(this.history.maxGeneration)
            hist.done = copyHistoryArray(histData.done.slice(0), null, true)
            hist.undone = copyHistoryArray(histData.undone.slice(0), null, true)
        },

        addLineClass: docMethodOp(function(handle, where, cls) {
            return changeLine(this, handle, where == "gutter" ? "gutter" : "class", function (line) {
                var prop = where == "text" ? "textClass"
                    : where == "background" ? "bgClass"
                    : where == "gutter" ? "gutterClass" : "wrapClass"
                if (!line[prop]) { line[prop] = cls }
                else if (classTest(cls).test(line[prop])) { return false }
                else { line[prop] += " " + cls }
                return true
            })
        }),
        removeLineClass: docMethodOp(function(handle, where, cls) {
            return changeLine(this, handle, where == "gutter" ? "gutter" : "class", function (line) {
                var prop = where == "text" ? "textClass"
                    : where == "background" ? "bgClass"
                    : where == "gutter" ? "gutterClass" : "wrapClass"
                var cur = line[prop]
                if (!cur) { return false }
                else if (cls == null) { line[prop] = null }
                else {
                    var found = cur.match(classTest(cls))
                    if (!found) { return false }
                    var end = found.index + found[0].length
                    line[prop] = cur.slice(0, found.index) + (!found.index || end == cur.length ? "" : " ") + cur.slice(end) || null
                }
                return true
            })
        }),

        addLineWidget: docMethodOp(function(handle, node, options) {
            return addLineWidget(this, handle, node, options)
        }),
        removeLineWidget: function(widget) { widget.clear() },

        markText: function(from, to, options) {
            return markText(this, clipPos(this, from), clipPos(this, to), options, options && options.type || "range")
        },
        setBookmark: function(pos, options) {
            var realOpts = {replacedWith: options && (options.nodeType == null ? options.widget : options),
                insertLeft: options && options.insertLeft,
                clearWhenEmpty: false, shared: options && options.shared,
                handleMouseEvents: options && options.handleMouseEvents}
            pos = clipPos(this, pos)
            return markText(this, pos, pos, realOpts, "bookmark")
        },
        findMarksAt: function(pos) {
            pos = clipPos(this, pos)
            var markers = [], spans = getLine(this, pos.line).markedSpans
            if (spans) { for (var i = 0; i < spans.length; ++i) {
                var span = spans[i]
                if ((span.from == null || span.from <= pos.ch) &&
                    (span.to == null || span.to >= pos.ch))
                { markers.push(span.marker.parent || span.marker) }
            } }
            return markers
        },
        findMarks: function(from, to, filter) {
            from = clipPos(this, from); to = clipPos(this, to)
            var found = [], lineNo$$1 = from.line
            this.iter(from.line, to.line + 1, function (line) {
                var spans = line.markedSpans
                if (spans) { for (var i = 0; i < spans.length; i++) {
                    var span = spans[i]
                    if (!(span.to != null && lineNo$$1 == from.line && from.ch >= span.to ||
                        span.from == null && lineNo$$1 != from.line ||
                        span.from != null && lineNo$$1 == to.line && span.from >= to.ch) &&
                        (!filter || filter(span.marker)))
                    { found.push(span.marker.parent || span.marker) }
                } }
                ++lineNo$$1
            })
            return found
        },
        getAllMarks: function() {
            var markers = []
            this.iter(function (line) {
                var sps = line.markedSpans
                if (sps) { for (var i = 0; i < sps.length; ++i)
                { if (sps[i].from != null) { markers.push(sps[i].marker) } } }
            })
            return markers
        },

        posFromIndex: function(off) {
            var ch, lineNo$$1 = this.first, sepSize = this.lineSeparator().length
            this.iter(function (line) {
                var sz = line.text.length + sepSize
                if (sz > off) { ch = off; return true }
                off -= sz
                ++lineNo$$1
            })
            return clipPos(this, Pos(lineNo$$1, ch))
        },
        indexFromPos: function (coords) {
            coords = clipPos(this, coords)
            var index = coords.ch
            if (coords.line < this.first || coords.ch < 0) { return 0 }
            var sepSize = this.lineSeparator().length
            this.iter(this.first, coords.line, function (line) { // iter aborts when callback returns a truthy value
                index += line.text.length + sepSize
            })
            return index
        },

        copy: function(copyHistory) {
            var doc = new Doc(getLines(this, this.first, this.first + this.size),
                this.modeOption, this.first, this.lineSep)
            doc.scrollTop = this.scrollTop; doc.scrollLeft = this.scrollLeft
            doc.sel = this.sel
            doc.extend = false
            if (copyHistory) {
                doc.history.undoDepth = this.history.undoDepth
                doc.setHistory(this.getHistory())
            }
            return doc
        },

        linkedDoc: function(options) {
            if (!options) { options = {} }
            var from = this.first, to = this.first + this.size
            if (options.from != null && options.from > from) { from = options.from }
            if (options.to != null && options.to < to) { to = options.to }
            var copy = new Doc(getLines(this, from, to), options.mode || this.modeOption, from, this.lineSep)
            if (options.sharedHist) { copy.history = this.history
            ; }(this.linked || (this.linked = [])).push({doc: copy, sharedHist: options.sharedHist})
            copy.linked = [{doc: this, isParent: true, sharedHist: options.sharedHist}]
            copySharedMarkers(copy, findSharedMarkers(this))
            return copy
        },
        unlinkDoc: function(other) {
            var this$1 = this;

            if (other instanceof CodeMirror$1) { other = other.doc }
            if (this.linked) { for (var i = 0; i < this.linked.length; ++i) {
                var link = this$1.linked[i]
                if (link.doc != other) { continue }
                this$1.linked.splice(i, 1)
                other.unlinkDoc(this$1)
                detachSharedMarkers(findSharedMarkers(this$1))
                break
            } }
            // If the histories were shared, split them again
            if (other.history == this.history) {
                var splitIds = [other.id]
                linkedDocs(other, function (doc) { return splitIds.push(doc.id); }, true)
                other.history = new History(null)
                other.history.done = copyHistoryArray(this.history.done, splitIds)
                other.history.undone = copyHistoryArray(this.history.undone, splitIds)
            }
        },
        iterLinkedDocs: function(f) {linkedDocs(this, f)},

        getMode: function() {return this.mode},
        getEditor: function() {return this.cm},

        splitLines: function(str) {
            if (this.lineSep) { return str.split(this.lineSep) }
            return splitLinesAuto(str)
        },
        lineSeparator: function() { return this.lineSep || "\n" }
    })

// Public alias.
    Doc.prototype.eachLine = Doc.prototype.iter

// Kludge to work around strange IE behavior where it'll sometimes
// re-fire a series of drag-related events right after the drop (#1551)
    var lastDrop = 0

    function onDrop(e) {
        var cm = this
        clearDragCursor(cm)
        if (signalDOMEvent(cm, e) || eventInWidget(cm.display, e))
        { return }
        e_preventDefault(e)
        if (ie) { lastDrop = +new Date }
        var pos = posFromMouse(cm, e, true), files = e.dataTransfer.files
        if (!pos || cm.isReadOnly()) { return }
        // Might be a file drop, in which case we simply extract the text
        // and insert it.
        if (files && files.length && window.FileReader && window.File) {
            var n = files.length, text = Array(n), read = 0
            var loadFile = function (file, i) {
                if (cm.options.allowDropFileTypes &&
                    indexOf(cm.options.allowDropFileTypes, file.type) == -1)
                { return }

                var reader = new FileReader
                reader.onload = operation(cm, function () {
                    var content = reader.result
                    if (/[\x00-\x08\x0e-\x1f]{2}/.test(content)) { content = "" }
                    text[i] = content
                    if (++read == n) {
                        pos = clipPos(cm.doc, pos)
                        var change = {from: pos, to: pos,
                            text: cm.doc.splitLines(text.join(cm.doc.lineSeparator())),
                            origin: "paste"}
                        makeChange(cm.doc, change)
                        setSelectionReplaceHistory(cm.doc, simpleSelection(pos, changeEnd(change)))
                    }
                })
                reader.readAsText(file)
            }
            for (var i = 0; i < n; ++i) { loadFile(files[i], i) }
        } else { // Normal drop
            // Don't do a replace if the drop happened inside of the selected text.
            if (cm.state.draggingText && cm.doc.sel.contains(pos) > -1) {
                cm.state.draggingText(e)
                // Ensure the editor is re-focused
                setTimeout(function () { return cm.display.input.focus(); }, 20)
                return
            }
            try {
                var text$1 = e.dataTransfer.getData("Text")
                if (text$1) {
                    var selected
                    if (cm.state.draggingText && !cm.state.draggingText.copy)
                    { selected = cm.listSelections() }
                    setSelectionNoUndo(cm.doc, simpleSelection(pos, pos))
                    if (selected) { for (var i$1 = 0; i$1 < selected.length; ++i$1)
                    { replaceRange(cm.doc, "", selected[i$1].anchor, selected[i$1].head, "drag") } }
                    cm.replaceSelection(text$1, "around", "paste")
                    cm.display.input.focus()
                }
            }
            catch(e){}
        }
    }

    function onDragStart(cm, e) {
        if (ie && (!cm.state.draggingText || +new Date - lastDrop < 100)) { e_stop(e); return }
        if (signalDOMEvent(cm, e) || eventInWidget(cm.display, e)) { return }

        e.dataTransfer.setData("Text", cm.getSelection())
        e.dataTransfer.effectAllowed = "copyMove"

        // Use dummy image instead of default browsers image.
        // Recent Safari (~6.0.2) have a tendency to segfault when this happens, so we don't do it there.
        if (e.dataTransfer.setDragImage && !safari) {
            var img = elt("img", null, null, "position: fixed; left: 0; top: 0;")
            img.src = "data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="
            if (presto) {
                img.width = img.height = 1
                cm.display.wrapper.appendChild(img)
                // Force a relayout, or Opera won't use our image for some obscure reason
                img._top = img.offsetTop
            }
            e.dataTransfer.setDragImage(img, 0, 0)
            if (presto) { img.parentNode.removeChild(img) }
        }
    }

    function onDragOver(cm, e) {
        var pos = posFromMouse(cm, e)
        if (!pos) { return }
        var frag = document.createDocumentFragment()
        drawSelectionCursor(cm, pos, frag)
        if (!cm.display.dragCursor) {
            cm.display.dragCursor = elt("div", null, "CodeMirror-cursors CodeMirror-dragcursors")
            cm.display.lineSpace.insertBefore(cm.display.dragCursor, cm.display.cursorDiv)
        }
        removeChildrenAndAdd(cm.display.dragCursor, frag)
    }

    function clearDragCursor(cm) {
        if (cm.display.dragCursor) {
            cm.display.lineSpace.removeChild(cm.display.dragCursor)
            cm.display.dragCursor = null
        }
    }

// These must be handled carefully, because naively registering a
// handler for each editor will cause the editors to never be
// garbage collected.

    function forEachCodeMirror(f) {
        if (!document.body.getElementsByClassName) { return }
        var byClass = document.body.getElementsByClassName("CodeMirror")
        for (var i = 0; i < byClass.length; i++) {
            var cm = byClass[i].CodeMirror
            if (cm) { f(cm) }
        }
    }

    var globalsRegistered = false
    function ensureGlobalHandlers() {
        if (globalsRegistered) { return }
        registerGlobalHandlers()
        globalsRegistered = true
    }
    function registerGlobalHandlers() {
        // When the window resizes, we need to refresh active editors.
        var resizeTimer
        on(window, "resize", function () {
            if (resizeTimer == null) { resizeTimer = setTimeout(function () {
                resizeTimer = null
                forEachCodeMirror(onResize)
            }, 100) }
        })
        // When the window loses focus, we want to show the editor as blurred
        on(window, "blur", function () { return forEachCodeMirror(onBlur); })
    }
// Called when the window resizes
    function onResize(cm) {
        var d = cm.display
        if (d.lastWrapHeight == d.wrapper.clientHeight && d.lastWrapWidth == d.wrapper.clientWidth)
        { return }
        // Might be a text scaling operation, clear size caches.
        d.cachedCharWidth = d.cachedTextHeight = d.cachedPaddingH = null
        d.scrollbarsClipped = false
        cm.setSize()
    }

    var keyNames = {
        3: "Enter", 8: "Backspace", 9: "Tab", 13: "Enter", 16: "Shift", 17: "Ctrl", 18: "Alt",
        19: "Pause", 20: "CapsLock", 27: "Esc", 32: "Space", 33: "PageUp", 34: "PageDown", 35: "End",
        36: "Home", 37: "Left", 38: "Up", 39: "Right", 40: "Down", 44: "PrintScrn", 45: "Insert",
        46: "Delete", 59: ";", 61: "=", 91: "Mod", 92: "Mod", 93: "Mod",
        106: "*", 107: "=", 109: "-", 110: ".", 111: "/", 127: "Delete",
        173: "-", 186: ";", 187: "=", 188: ",", 189: "-", 190: ".", 191: "/", 192: "`", 219: "[", 220: "\\",
        221: "]", 222: "'", 63232: "Up", 63233: "Down", 63234: "Left", 63235: "Right", 63272: "Delete",
        63273: "Home", 63275: "End", 63276: "PageUp", 63277: "PageDown", 63302: "Insert"
    }

// Number keys
    for (var i = 0; i < 10; i++) { keyNames[i + 48] = keyNames[i + 96] = String(i) }
// Alphabetic keys
    for (var i$1 = 65; i$1 <= 90; i$1++) { keyNames[i$1] = String.fromCharCode(i$1) }
// Function keys
    for (var i$2 = 1; i$2 <= 12; i$2++) { keyNames[i$2 + 111] = keyNames[i$2 + 63235] = "F" + i$2 }

    var keyMap = {}

    keyMap.basic = {
        "Left": "goCharLeft", "Right": "goCharRight", "Up": "goLineUp", "Down": "goLineDown",
        "End": "goLineEnd", "Home": "goLineStartSmart", "PageUp": "goPageUp", "PageDown": "goPageDown",
        "Delete": "delCharAfter", "Backspace": "delCharBefore", "Shift-Backspace": "delCharBefore",
        "Tab": "defaultTab", "Shift-Tab": "indentAuto",
        "Enter": "newlineAndIndent", "Insert": "toggleOverwrite",
        "Esc": "singleSelection"
    }
// Note that the save and find-related commands aren't defined by
// default. User code or addons can define them. Unknown commands
// are simply ignored.
    keyMap.pcDefault = {
        "Ctrl-A": "selectAll", "Ctrl-D": "deleteLine", "Ctrl-Z": "undo", "Shift-Ctrl-Z": "redo", "Ctrl-Y": "redo",
        "Ctrl-Home": "goDocStart", "Ctrl-End": "goDocEnd", "Ctrl-Up": "goLineUp", "Ctrl-Down": "goLineDown",
        "Ctrl-Left": "goGroupLeft", "Ctrl-Right": "goGroupRight", "Alt-Left": "goLineStart", "Alt-Right": "goLineEnd",
        "Ctrl-Backspace": "delGroupBefore", "Ctrl-Delete": "delGroupAfter", "Ctrl-S": "save", "Ctrl-F": "find",
        "Ctrl-G": "findNext", "Shift-Ctrl-G": "findPrev", "Shift-Ctrl-F": "replace", "Shift-Ctrl-R": "replaceAll",
        "Ctrl-[": "indentLess", "Ctrl-]": "indentMore",
        "Ctrl-U": "undoSelection", "Shift-Ctrl-U": "redoSelection", "Alt-U": "redoSelection",
        fallthrough: "basic"
    }
// Very basic readline/emacs-style bindings, which are standard on Mac.
    keyMap.emacsy = {
        "Ctrl-F": "goCharRight", "Ctrl-B": "goCharLeft", "Ctrl-P": "goLineUp", "Ctrl-N": "goLineDown",
        "Alt-F": "goWordRight", "Alt-B": "goWordLeft", "Ctrl-A": "goLineStart", "Ctrl-E": "goLineEnd",
        "Ctrl-V": "goPageDown", "Shift-Ctrl-V": "goPageUp", "Ctrl-D": "delCharAfter", "Ctrl-H": "delCharBefore",
        "Alt-D": "delWordAfter", "Alt-Backspace": "delWordBefore", "Ctrl-K": "killLine", "Ctrl-T": "transposeChars",
        "Ctrl-O": "openLine"
    }
    keyMap.macDefault = {
        "Cmd-A": "selectAll", "Cmd-D": "deleteLine", "Cmd-Z": "undo", "Shift-Cmd-Z": "redo", "Cmd-Y": "redo",
        "Cmd-Home": "goDocStart", "Cmd-Up": "goDocStart", "Cmd-End": "goDocEnd", "Cmd-Down": "goDocEnd", "Alt-Left": "goGroupLeft",
        "Alt-Right": "goGroupRight", "Cmd-Left": "goLineLeft", "Cmd-Right": "goLineRight", "Alt-Backspace": "delGroupBefore",
        "Ctrl-Alt-Backspace": "delGroupAfter", "Alt-Delete": "delGroupAfter", "Cmd-S": "save", "Cmd-F": "find",
        "Cmd-G": "findNext", "Shift-Cmd-G": "findPrev", "Cmd-Alt-F": "replace", "Shift-Cmd-Alt-F": "replaceAll",
        "Cmd-[": "indentLess", "Cmd-]": "indentMore", "Cmd-Backspace": "delWrappedLineLeft", "Cmd-Delete": "delWrappedLineRight",
        "Cmd-U": "undoSelection", "Shift-Cmd-U": "redoSelection", "Ctrl-Up": "goDocStart", "Ctrl-Down": "goDocEnd",
        fallthrough: ["basic", "emacsy"]
    }
    keyMap["default"] = mac ? keyMap.macDefault : keyMap.pcDefault

// KEYMAP DISPATCH

    function normalizeKeyName(name) {
        var parts = name.split(/-(?!$)/)
        name = parts[parts.length - 1]
        var alt, ctrl, shift, cmd
        for (var i = 0; i < parts.length - 1; i++) {
            var mod = parts[i]
            if (/^(cmd|meta|m)$/i.test(mod)) { cmd = true }
            else if (/^a(lt)?$/i.test(mod)) { alt = true }
            else if (/^(c|ctrl|control)$/i.test(mod)) { ctrl = true }
            else if (/^s(hift)?$/i.test(mod)) { shift = true }
            else { throw new Error("Unrecognized modifier name: " + mod) }
        }
        if (alt) { name = "Alt-" + name }
        if (ctrl) { name = "Ctrl-" + name }
        if (cmd) { name = "Cmd-" + name }
        if (shift) { name = "Shift-" + name }
        return name
    }

// This is a kludge to keep keymaps mostly working as raw objects
// (backwards compatibility) while at the same time support features
// like normalization and multi-stroke key bindings. It compiles a
// new normalized keymap, and then updates the old object to reflect
// this.
    function normalizeKeyMap(keymap) {
        var copy = {}
        for (var keyname in keymap) { if (keymap.hasOwnProperty(keyname)) {
            var value = keymap[keyname]
            if (/^(name|fallthrough|(de|at)tach)$/.test(keyname)) { continue }
            if (value == "...") { delete keymap[keyname]; continue }

            var keys = map(keyname.split(" "), normalizeKeyName)
            for (var i = 0; i < keys.length; i++) {
                var val = void 0, name = void 0
                if (i == keys.length - 1) {
                    name = keys.join(" ")
                    val = value
                } else {
                    name = keys.slice(0, i + 1).join(" ")
                    val = "..."
                }
                var prev = copy[name]
                if (!prev) { copy[name] = val }
                else if (prev != val) { throw new Error("Inconsistent bindings for " + name) }
            }
            delete keymap[keyname]
        } }
        for (var prop in copy) { keymap[prop] = copy[prop] }
        return keymap
    }

    function lookupKey(key, map$$1, handle, context) {
        map$$1 = getKeyMap(map$$1)
        var found = map$$1.call ? map$$1.call(key, context) : map$$1[key]
        if (found === false) { return "nothing" }
        if (found === "...") { return "multi" }
        if (found != null && handle(found)) { return "handled" }

        if (map$$1.fallthrough) {
            if (Object.prototype.toString.call(map$$1.fallthrough) != "[object Array]")
            { return lookupKey(key, map$$1.fallthrough, handle, context) }
            for (var i = 0; i < map$$1.fallthrough.length; i++) {
                var result = lookupKey(key, map$$1.fallthrough[i], handle, context)
                if (result) { return result }
            }
        }
    }

// Modifier key presses don't count as 'real' key presses for the
// purpose of keymap fallthrough.
    function isModifierKey(value) {
        var name = typeof value == "string" ? value : keyNames[value.keyCode]
        return name == "Ctrl" || name == "Alt" || name == "Shift" || name == "Mod"
    }

// Look up the name of a key as indicated by an event object.
    function keyName(event, noShift) {
        if (presto && event.keyCode == 34 && event["char"]) { return false }
        var base = keyNames[event.keyCode], name = base
        if (name == null || event.altGraphKey) { return false }
        if (event.altKey && base != "Alt") { name = "Alt-" + name }
        if ((flipCtrlCmd ? event.metaKey : event.ctrlKey) && base != "Ctrl") { name = "Ctrl-" + name }
        if ((flipCtrlCmd ? event.ctrlKey : event.metaKey) && base != "Cmd") { name = "Cmd-" + name }
        if (!noShift && event.shiftKey && base != "Shift") { name = "Shift-" + name }
        return name
    }

    function getKeyMap(val) {
        return typeof val == "string" ? keyMap[val] : val
    }

// Helper for deleting text near the selection(s), used to implement
// backspace, delete, and similar functionality.
    function deleteNearSelection(cm, compute) {
        var ranges = cm.doc.sel.ranges, kill = []
        // Build up a set of ranges to kill first, merging overlapping
        // ranges.
        for (var i = 0; i < ranges.length; i++) {
            var toKill = compute(ranges[i])
            while (kill.length && cmp(toKill.from, lst(kill).to) <= 0) {
                var replaced = kill.pop()
                if (cmp(replaced.from, toKill.from) < 0) {
                    toKill.from = replaced.from
                    break
                }
            }
            kill.push(toKill)
        }
        // Next, remove those actual ranges.
        runInOp(cm, function () {
            for (var i = kill.length - 1; i >= 0; i--)
            { replaceRange(cm.doc, "", kill[i].from, kill[i].to, "+delete") }
            ensureCursorVisible(cm)
        })
    }

// Commands are parameter-less actions that can be performed on an
// editor, mostly used for keybindings.
    var commands = {
        selectAll: selectAll,
        singleSelection: function (cm) { return cm.setSelection(cm.getCursor("anchor"), cm.getCursor("head"), sel_dontScroll); },
        killLine: function (cm) { return deleteNearSelection(cm, function (range) {
            if (range.empty()) {
                var len = getLine(cm.doc, range.head.line).text.length
                if (range.head.ch == len && range.head.line < cm.lastLine())
                { return {from: range.head, to: Pos(range.head.line + 1, 0)} }
                else
                { return {from: range.head, to: Pos(range.head.line, len)} }
            } else {
                return {from: range.from(), to: range.to()}
            }
        }); },
        deleteLine: function (cm) { return deleteNearSelection(cm, function (range) { return ({
            from: Pos(range.from().line, 0),
            to: clipPos(cm.doc, Pos(range.to().line + 1, 0))
        }); }); },
        delLineLeft: function (cm) { return deleteNearSelection(cm, function (range) { return ({
            from: Pos(range.from().line, 0), to: range.from()
        }); }); },
        delWrappedLineLeft: function (cm) { return deleteNearSelection(cm, function (range) {
            var top = cm.charCoords(range.head, "div").top + 5
            var leftPos = cm.coordsChar({left: 0, top: top}, "div")
            return {from: leftPos, to: range.from()}
        }); },
        delWrappedLineRight: function (cm) { return deleteNearSelection(cm, function (range) {
            var top = cm.charCoords(range.head, "div").top + 5
            var rightPos = cm.coordsChar({left: cm.display.lineDiv.offsetWidth + 100, top: top}, "div")
            return {from: range.from(), to: rightPos }
        }); },
        undo: function (cm) { return cm.undo(); },
        redo: function (cm) { return cm.redo(); },
        undoSelection: function (cm) { return cm.undoSelection(); },
        redoSelection: function (cm) { return cm.redoSelection(); },
        goDocStart: function (cm) { return cm.extendSelection(Pos(cm.firstLine(), 0)); },
        goDocEnd: function (cm) { return cm.extendSelection(Pos(cm.lastLine())); },
        goLineStart: function (cm) { return cm.extendSelectionsBy(function (range) { return lineStart(cm, range.head.line); },
            {origin: "+move", bias: 1}
        ); },
        goLineStartSmart: function (cm) { return cm.extendSelectionsBy(function (range) { return lineStartSmart(cm, range.head); },
            {origin: "+move", bias: 1}
        ); },
        goLineEnd: function (cm) { return cm.extendSelectionsBy(function (range) { return lineEnd(cm, range.head.line); },
            {origin: "+move", bias: -1}
        ); },
        goLineRight: function (cm) { return cm.extendSelectionsBy(function (range) {
            var top = cm.charCoords(range.head, "div").top + 5
            return cm.coordsChar({left: cm.display.lineDiv.offsetWidth + 100, top: top}, "div")
        }, sel_move); },
        goLineLeft: function (cm) { return cm.extendSelectionsBy(function (range) {
            var top = cm.charCoords(range.head, "div").top + 5
            return cm.coordsChar({left: 0, top: top}, "div")
        }, sel_move); },
        goLineLeftSmart: function (cm) { return cm.extendSelectionsBy(function (range) {
            var top = cm.charCoords(range.head, "div").top + 5
            var pos = cm.coordsChar({left: 0, top: top}, "div")
            if (pos.ch < cm.getLine(pos.line).search(/\S/)) { return lineStartSmart(cm, range.head) }
            return pos
        }, sel_move); },
        goLineUp: function (cm) { return cm.moveV(-1, "line"); },
        goLineDown: function (cm) { return cm.moveV(1, "line"); },
        goPageUp: function (cm) { return cm.moveV(-1, "page"); },
        goPageDown: function (cm) { return cm.moveV(1, "page"); },
        goCharLeft: function (cm) { return cm.moveH(-1, "char"); },
        goCharRight: function (cm) { return cm.moveH(1, "char"); },
        goColumnLeft: function (cm) { return cm.moveH(-1, "column"); },
        goColumnRight: function (cm) { return cm.moveH(1, "column"); },
        goWordLeft: function (cm) { return cm.moveH(-1, "word"); },
        goGroupRight: function (cm) { return cm.moveH(1, "group"); },
        goGroupLeft: function (cm) { return cm.moveH(-1, "group"); },
        goWordRight: function (cm) { return cm.moveH(1, "word"); },
        delCharBefore: function (cm) { return cm.deleteH(-1, "char"); },
        delCharAfter: function (cm) { return cm.deleteH(1, "char"); },
        delWordBefore: function (cm) { return cm.deleteH(-1, "word"); },
        delWordAfter: function (cm) { return cm.deleteH(1, "word"); },
        delGroupBefore: function (cm) { return cm.deleteH(-1, "group"); },
        delGroupAfter: function (cm) { return cm.deleteH(1, "group"); },
        indentAuto: function (cm) { return cm.indentSelection("smart"); },
        indentMore: function (cm) { return cm.indentSelection("add"); },
        indentLess: function (cm) { return cm.indentSelection("subtract"); },
        insertTab: function (cm) { return cm.replaceSelection("\t"); },
        insertSoftTab: function (cm) {
            var spaces = [], ranges = cm.listSelections(), tabSize = cm.options.tabSize
            for (var i = 0; i < ranges.length; i++) {
                var pos = ranges[i].from()
                var col = countColumn(cm.getLine(pos.line), pos.ch, tabSize)
                spaces.push(spaceStr(tabSize - col % tabSize))
            }
            cm.replaceSelections(spaces)
        },
        defaultTab: function (cm) {
            if (cm.somethingSelected()) { cm.indentSelection("add") }
            else { cm.execCommand("insertTab") }
        },
        // Swap the two chars left and right of each selection's head.
        // Move cursor behind the two swapped characters afterwards.
        //
        // Doesn't consider line feeds a character.
        // Doesn't scan more than one line above to find a character.
        // Doesn't do anything on an empty line.
        // Doesn't do anything with non-empty selections.
        transposeChars: function (cm) { return runInOp(cm, function () {
            var ranges = cm.listSelections(), newSel = []
            for (var i = 0; i < ranges.length; i++) {
                if (!ranges[i].empty()) { continue }
                var cur = ranges[i].head, line = getLine(cm.doc, cur.line).text
                if (line) {
                    if (cur.ch == line.length) { cur = new Pos(cur.line, cur.ch - 1) }
                    if (cur.ch > 0) {
                        cur = new Pos(cur.line, cur.ch + 1)
                        cm.replaceRange(line.charAt(cur.ch - 1) + line.charAt(cur.ch - 2),
                            Pos(cur.line, cur.ch - 2), cur, "+transpose")
                    } else if (cur.line > cm.doc.first) {
                        var prev = getLine(cm.doc, cur.line - 1).text
                        if (prev) {
                            cur = new Pos(cur.line, 1)
                            cm.replaceRange(line.charAt(0) + cm.doc.lineSeparator() +
                                prev.charAt(prev.length - 1),
                                Pos(cur.line - 1, prev.length - 1), cur, "+transpose")
                        }
                    }
                }
                newSel.push(new Range(cur, cur))
            }
            cm.setSelections(newSel)
        }); },
        newlineAndIndent: function (cm) { return runInOp(cm, function () {
            var sels = cm.listSelections()
            for (var i = sels.length - 1; i >= 0; i--)
            { cm.replaceRange(cm.doc.lineSeparator(), sels[i].anchor, sels[i].head, "+input") }
            sels = cm.listSelections()
            for (var i$1 = 0; i$1 < sels.length; i$1++)
            { cm.indentLine(sels[i$1].from().line, null, true) }
            ensureCursorVisible(cm)
        }); },
        openLine: function (cm) { return cm.replaceSelection("\n", "start"); },
        toggleOverwrite: function (cm) { return cm.toggleOverwrite(); }
    }


    function lineStart(cm, lineN) {
        var line = getLine(cm.doc, lineN)
        var visual = visualLine(line)
        if (visual != line) { lineN = lineNo(visual) }
        var order = getOrder(visual)
        var ch = !order ? 0 : order[0].level % 2 ? lineRight(visual) : lineLeft(visual)
        return Pos(lineN, ch)
    }
    function lineEnd(cm, lineN) {
        var merged, line = getLine(cm.doc, lineN)
        while (merged = collapsedSpanAtEnd(line)) {
            line = merged.find(1, true).line
            lineN = null
        }
        var order = getOrder(line)
        var ch = !order ? line.text.length : order[0].level % 2 ? lineLeft(line) : lineRight(line)
        return Pos(lineN == null ? lineNo(line) : lineN, ch)
    }
    function lineStartSmart(cm, pos) {
        var start = lineStart(cm, pos.line)
        var line = getLine(cm.doc, start.line)
        var order = getOrder(line)
        if (!order || order[0].level == 0) {
            var firstNonWS = Math.max(0, line.text.search(/\S/))
            var inWS = pos.line == start.line && pos.ch <= firstNonWS && pos.ch
            return Pos(start.line, inWS ? 0 : firstNonWS)
        }
        return start
    }

// Run a handler that was bound to a key.
    function doHandleBinding(cm, bound, dropShift) {
        if (typeof bound == "string") {
            bound = commands[bound]
            if (!bound) { return false }
        }
        // Ensure previous input has been read, so that the handler sees a
        // consistent view of the document
        cm.display.input.ensurePolled()
        var prevShift = cm.display.shift, done = false
        try {
            if (cm.isReadOnly()) { cm.state.suppressEdits = true }
            if (dropShift) { cm.display.shift = false }
            done = bound(cm) != Pass
        } finally {
            cm.display.shift = prevShift
            cm.state.suppressEdits = false
        }
        return done
    }

    function lookupKeyForEditor(cm, name, handle) {
        for (var i = 0; i < cm.state.keyMaps.length; i++) {
            var result = lookupKey(name, cm.state.keyMaps[i], handle, cm)
            if (result) { return result }
        }
        return (cm.options.extraKeys && lookupKey(name, cm.options.extraKeys, handle, cm))
            || lookupKey(name, cm.options.keyMap, handle, cm)
    }

    var stopSeq = new Delayed
    function dispatchKey(cm, name, e, handle) {
        var seq = cm.state.keySeq
        if (seq) {
            if (isModifierKey(name)) { return "handled" }
            stopSeq.set(50, function () {
                if (cm.state.keySeq == seq) {
                    cm.state.keySeq = null
                    cm.display.input.reset()
                }
            })
            name = seq + " " + name
        }
        var result = lookupKeyForEditor(cm, name, handle)

        if (result == "multi")
        { cm.state.keySeq = name }
        if (result == "handled")
        { signalLater(cm, "keyHandled", cm, name, e) }

        if (result == "handled" || result == "multi") {
            e_preventDefault(e)
            restartBlink(cm)
        }

        if (seq && !result && /\'$/.test(name)) {
            e_preventDefault(e)
            return true
        }
        return !!result
    }

// Handle a key from the keydown event.
    function handleKeyBinding(cm, e) {
        var name = keyName(e, true)
        if (!name) { return false }

        if (e.shiftKey && !cm.state.keySeq) {
            // First try to resolve full name (including 'Shift-'). Failing
            // that, see if there is a cursor-motion command (starting with
            // 'go') bound to the keyname without 'Shift-'.
            return dispatchKey(cm, "Shift-" + name, e, function (b) { return doHandleBinding(cm, b, true); })
                || dispatchKey(cm, name, e, function (b) {
                    if (typeof b == "string" ? /^go[A-Z]/.test(b) : b.motion)
                    { return doHandleBinding(cm, b) }
                })
        } else {
            return dispatchKey(cm, name, e, function (b) { return doHandleBinding(cm, b); })
        }
    }

// Handle a key from the keypress event
    function handleCharBinding(cm, e, ch) {
        return dispatchKey(cm, "'" + ch + "'", e, function (b) { return doHandleBinding(cm, b, true); })
    }

    var lastStoppedKey = null
    function onKeyDown(e) {
        var cm = this
        cm.curOp.focus = activeElt()
        if (signalDOMEvent(cm, e)) { return }
        // IE does strange things with escape.
        if (ie && ie_version < 11 && e.keyCode == 27) { e.returnValue = false }
        var code = e.keyCode
        cm.display.shift = code == 16 || e.shiftKey
        var handled = handleKeyBinding(cm, e)
        if (presto) {
            lastStoppedKey = handled ? code : null
            // Opera has no cut event... we try to at least catch the key combo
            if (!handled && code == 88 && !hasCopyEvent && (mac ? e.metaKey : e.ctrlKey))
            { cm.replaceSelection("", null, "cut") }
        }

        // Turn mouse into crosshair when Alt is held on Mac.
        if (code == 18 && !/\bCodeMirror-crosshair\b/.test(cm.display.lineDiv.className))
        { showCrossHair(cm) }
    }

    function showCrossHair(cm) {
        var lineDiv = cm.display.lineDiv
        addClass(lineDiv, "CodeMirror-crosshair")

        function up(e) {
            if (e.keyCode == 18 || !e.altKey) {
                rmClass(lineDiv, "CodeMirror-crosshair")
                off(document, "keyup", up)
                off(document, "mouseover", up)
            }
        }
        on(document, "keyup", up)
        on(document, "mouseover", up)
    }

    function onKeyUp(e) {
        if (e.keyCode == 16) { this.doc.sel.shift = false }
        signalDOMEvent(this, e)
    }

    function onKeyPress(e) {
        var cm = this
        if (eventInWidget(cm.display, e) || signalDOMEvent(cm, e) || e.ctrlKey && !e.altKey || mac && e.metaKey) { return }
        var keyCode = e.keyCode, charCode = e.charCode
        if (presto && keyCode == lastStoppedKey) {lastStoppedKey = null; e_preventDefault(e); return}
        if ((presto && (!e.which || e.which < 10)) && handleKeyBinding(cm, e)) { return }
        var ch = String.fromCharCode(charCode == null ? keyCode : charCode)
        // Some browsers fire keypress events for backspace
        if (ch == "\x08") { return }
        if (handleCharBinding(cm, e, ch)) { return }
        cm.display.input.onKeyPress(e)
    }

// A mouse down can be a single click, double click, triple click,
// start of selection drag, start of text drag, new cursor
// (ctrl-click), rectangle drag (alt-drag), or xwin
// middle-click-paste. Or it might be a click on something we should
// not interfere with, such as a scrollbar or widget.
    function onMouseDown(e) {
        var cm = this, display = cm.display
        if (signalDOMEvent(cm, e) || display.activeTouch && display.input.supportsTouch()) { return }
        display.shift = e.shiftKey

        if (eventInWidget(display, e)) {
            if (!webkit) {
                // Briefly turn off draggability, to allow widgets to do
                // normal dragging things.
                display.scroller.draggable = false
                setTimeout(function () { return display.scroller.draggable = true; }, 100)
            }
            return
        }
        if (clickInGutter(cm, e)) { return }
        var start = posFromMouse(cm, e)
        window.focus()

        switch (e_button(e)) {
            case 1:
                // #3261: make sure, that we're not starting a second selection
                if (cm.state.selectingText)
                { cm.state.selectingText(e) }
                else if (start)
                { leftButtonDown(cm, e, start) }
                else if (e_target(e) == display.scroller)
                { e_preventDefault(e) }
                break
            case 2:
                if (webkit) { cm.state.lastMiddleDown = +new Date }
                if (start) { extendSelection(cm.doc, start) }
                setTimeout(function () { return display.input.focus(); }, 20)
                e_preventDefault(e)
                break
            case 3:
                if (captureRightClick) { onContextMenu(cm, e) }
                else { delayBlurEvent(cm) }
                break
        }
    }

    var lastClick;
    var lastDoubleClick
    function leftButtonDown(cm, e, start) {
        if (ie) { setTimeout(bind(ensureFocus, cm), 0) }
        else { cm.curOp.focus = activeElt() }

        var now = +new Date, type
        if (lastDoubleClick && lastDoubleClick.time > now - 400 && cmp(lastDoubleClick.pos, start) == 0) {
            type = "triple"
        } else if (lastClick && lastClick.time > now - 400 && cmp(lastClick.pos, start) == 0) {
            type = "double"
            lastDoubleClick = {time: now, pos: start}
        } else {
            type = "single"
            lastClick = {time: now, pos: start}
        }

        var sel = cm.doc.sel, modifier = mac ? e.metaKey : e.ctrlKey, contained
        if (cm.options.dragDrop && dragAndDrop && !cm.isReadOnly() &&
            type == "single" && (contained = sel.contains(start)) > -1 &&
            (cmp((contained = sel.ranges[contained]).from(), start) < 0 || start.xRel > 0) &&
            (cmp(contained.to(), start) > 0 || start.xRel < 0))
        { leftButtonStartDrag(cm, e, start, modifier) }
        else
        { leftButtonSelect(cm, e, start, type, modifier) }
    }

// Start a text drag. When it ends, see if any dragging actually
// happen, and treat as a click if it didn't.
    function leftButtonStartDrag(cm, e, start, modifier) {
        var display = cm.display, startTime = +new Date
        var dragEnd = operation(cm, function (e2) {
            if (webkit) { display.scroller.draggable = false }
            cm.state.draggingText = false
            off(document, "mouseup", dragEnd)
            off(display.scroller, "drop", dragEnd)
            if (Math.abs(e.clientX - e2.clientX) + Math.abs(e.clientY - e2.clientY) < 10) {
                e_preventDefault(e2)
                if (!modifier && +new Date - 200 < startTime)
                { extendSelection(cm.doc, start) }
                // Work around unexplainable focus problem in IE9 (#2127) and Chrome (#3081)
                if (webkit || ie && ie_version == 9)
                { setTimeout(function () {document.body.focus(); display.input.focus()}, 20) }
                else
                { display.input.focus() }
            }
        })
        // Let the drag handler handle this.
        if (webkit) { display.scroller.draggable = true }
        cm.state.draggingText = dragEnd
        dragEnd.copy = mac ? e.altKey : e.ctrlKey
        // IE's approach to draggable
        if (display.scroller.dragDrop) { display.scroller.dragDrop() }
        on(document, "mouseup", dragEnd)
        on(display.scroller, "drop", dragEnd)
    }

// Normal selection, as opposed to text dragging.
    function leftButtonSelect(cm, e, start, type, addNew) {
        var display = cm.display, doc = cm.doc
        e_preventDefault(e)

        var ourRange, ourIndex, startSel = doc.sel, ranges = startSel.ranges
        if (addNew && !e.shiftKey) {
            ourIndex = doc.sel.contains(start)
            if (ourIndex > -1)
            { ourRange = ranges[ourIndex] }
            else
            { ourRange = new Range(start, start) }
        } else {
            ourRange = doc.sel.primary()
            ourIndex = doc.sel.primIndex
        }

        if (chromeOS ? e.shiftKey && e.metaKey : e.altKey) {
            type = "rect"
            if (!addNew) { ourRange = new Range(start, start) }
            start = posFromMouse(cm, e, true, true)
            ourIndex = -1
        } else if (type == "double") {
            var word = cm.findWordAt(start)
            if (cm.display.shift || doc.extend)
            { ourRange = extendRange(doc, ourRange, word.anchor, word.head) }
            else
            { ourRange = word }
        } else if (type == "triple") {
            var line = new Range(Pos(start.line, 0), clipPos(doc, Pos(start.line + 1, 0)))
            if (cm.display.shift || doc.extend)
            { ourRange = extendRange(doc, ourRange, line.anchor, line.head) }
            else
            { ourRange = line }
        } else {
            ourRange = extendRange(doc, ourRange, start)
        }

        if (!addNew) {
            ourIndex = 0
            setSelection(doc, new Selection([ourRange], 0), sel_mouse)
            startSel = doc.sel
        } else if (ourIndex == -1) {
            ourIndex = ranges.length
            setSelection(doc, normalizeSelection(ranges.concat([ourRange]), ourIndex),
                {scroll: false, origin: "*mouse"})
        } else if (ranges.length > 1 && ranges[ourIndex].empty() && type == "single" && !e.shiftKey) {
            setSelection(doc, normalizeSelection(ranges.slice(0, ourIndex).concat(ranges.slice(ourIndex + 1)), 0),
                {scroll: false, origin: "*mouse"})
            startSel = doc.sel
        } else {
            replaceOneSelection(doc, ourIndex, ourRange, sel_mouse)
        }

        var lastPos = start
        function extendTo(pos) {
            if (cmp(lastPos, pos) == 0) { return }
            lastPos = pos

            if (type == "rect") {
                var ranges = [], tabSize = cm.options.tabSize
                var startCol = countColumn(getLine(doc, start.line).text, start.ch, tabSize)
                var posCol = countColumn(getLine(doc, pos.line).text, pos.ch, tabSize)
                var left = Math.min(startCol, posCol), right = Math.max(startCol, posCol)
                for (var line = Math.min(start.line, pos.line), end = Math.min(cm.lastLine(), Math.max(start.line, pos.line));
                     line <= end; line++) {
                    var text = getLine(doc, line).text, leftPos = findColumn(text, left, tabSize)
                    if (left == right)
                    { ranges.push(new Range(Pos(line, leftPos), Pos(line, leftPos))) }
                    else if (text.length > leftPos)
                    { ranges.push(new Range(Pos(line, leftPos), Pos(line, findColumn(text, right, tabSize)))) }
                }
                if (!ranges.length) { ranges.push(new Range(start, start)) }
                setSelection(doc, normalizeSelection(startSel.ranges.slice(0, ourIndex).concat(ranges), ourIndex),
                    {origin: "*mouse", scroll: false})
                cm.scrollIntoView(pos)
            } else {
                var oldRange = ourRange
                var anchor = oldRange.anchor, head = pos
                if (type != "single") {
                    var range$$1
                    if (type == "double")
                    { range$$1 = cm.findWordAt(pos) }
                    else
                    { range$$1 = new Range(Pos(pos.line, 0), clipPos(doc, Pos(pos.line + 1, 0))) }
                    if (cmp(range$$1.anchor, anchor) > 0) {
                        head = range$$1.head
                        anchor = minPos(oldRange.from(), range$$1.anchor)
                    } else {
                        head = range$$1.anchor
                        anchor = maxPos(oldRange.to(), range$$1.head)
                    }
                }
                var ranges$1 = startSel.ranges.slice(0)
                ranges$1[ourIndex] = new Range(clipPos(doc, anchor), head)
                setSelection(doc, normalizeSelection(ranges$1, ourIndex), sel_mouse)
            }
        }

        var editorSize = display.wrapper.getBoundingClientRect()
        // Used to ensure timeout re-tries don't fire when another extend
        // happened in the meantime (clearTimeout isn't reliable -- at
        // least on Chrome, the timeouts still happen even when cleared,
        // if the clear happens after their scheduled firing time).
        var counter = 0

        function extend(e) {
            var curCount = ++counter
            var cur = posFromMouse(cm, e, true, type == "rect")
            if (!cur) { return }
            if (cmp(cur, lastPos) != 0) {
                cm.curOp.focus = activeElt()
                extendTo(cur)
                var visible = visibleLines(display, doc)
                if (cur.line >= visible.to || cur.line < visible.from)
                { setTimeout(operation(cm, function () {if (counter == curCount) { extend(e) }}), 150) }
            } else {
                var outside = e.clientY < editorSize.top ? -20 : e.clientY > editorSize.bottom ? 20 : 0
                if (outside) { setTimeout(operation(cm, function () {
                    if (counter != curCount) { return }
                    display.scroller.scrollTop += outside
                    extend(e)
                }), 50) }
            }
        }

        function done(e) {
            cm.state.selectingText = false
            counter = Infinity
            e_preventDefault(e)
            display.input.focus()
            off(document, "mousemove", move)
            off(document, "mouseup", up)
            doc.history.lastSelOrigin = null
        }

        var move = operation(cm, function (e) {
            if (!e_button(e)) { done(e) }
            else { extend(e) }
        })
        var up = operation(cm, done)
        cm.state.selectingText = up
        on(document, "mousemove", move)
        on(document, "mouseup", up)
    }


// Determines whether an event happened in the gutter, and fires the
// handlers for the corresponding event.
    function gutterEvent(cm, e, type, prevent) {
        var mX, mY
        try { mX = e.clientX; mY = e.clientY }
        catch(e) { return false }
        if (mX >= Math.floor(cm.display.gutters.getBoundingClientRect().right)) { return false }
        if (prevent) { e_preventDefault(e) }

        var display = cm.display
        var lineBox = display.lineDiv.getBoundingClientRect()

        if (mY > lineBox.bottom || !hasHandler(cm, type)) { return e_defaultPrevented(e) }
        mY -= lineBox.top - display.viewOffset

        for (var i = 0; i < cm.options.gutters.length; ++i) {
            var g = display.gutters.childNodes[i]
            if (g && g.getBoundingClientRect().right >= mX) {
                var line = lineAtHeight(cm.doc, mY)
                var gutter = cm.options.gutters[i]
                signal(cm, type, cm, line, gutter, e)
                return e_defaultPrevented(e)
            }
        }
    }

    function clickInGutter(cm, e) {
        return gutterEvent(cm, e, "gutterClick", true)
    }

// CONTEXT MENU HANDLING

// To make the context menu work, we need to briefly unhide the
// textarea (making it as unobtrusive as possible) to let the
// right-click take effect on it.
    function onContextMenu(cm, e) {
        if (eventInWidget(cm.display, e) || contextMenuInGutter(cm, e)) { return }
        if (signalDOMEvent(cm, e, "contextmenu")) { return }
        cm.display.input.onContextMenu(e)
    }

    function contextMenuInGutter(cm, e) {
        if (!hasHandler(cm, "gutterContextMenu")) { return false }
        return gutterEvent(cm, e, "gutterContextMenu", false)
    }

    function themeChanged(cm) {
        cm.display.wrapper.className = cm.display.wrapper.className.replace(/\s*cm-s-\S+/g, "") +
            cm.options.theme.replace(/(^|\s)\s*/g, " cm-s-")
        clearCaches(cm)
    }

    var Init = {toString: function(){return "CodeMirror.Init"}}

    var defaults = {}
    var optionHandlers = {}

    function defineOptions(CodeMirror) {
        var optionHandlers = CodeMirror.optionHandlers

        function option(name, deflt, handle, notOnInit) {
            CodeMirror.defaults[name] = deflt
            if (handle) { optionHandlers[name] =
                notOnInit ? function (cm, val, old) {if (old != Init) { handle(cm, val, old) }} : handle }
        }

        CodeMirror.defineOption = option

        // Passed to option handlers when there is no old value.
        CodeMirror.Init = Init

        // These two are, on init, called from the constructor because they
        // have to be initialized before the editor can start at all.
        option("value", "", function (cm, val) { return cm.setValue(val); }, true)
        option("mode", null, function (cm, val) {
            cm.doc.modeOption = val
            loadMode(cm)
        }, true)

        option("indentUnit", 2, loadMode, true)
        option("indentWithTabs", false)
        option("smartIndent", true)
        option("tabSize", 4, function (cm) {
            resetModeState(cm)
            clearCaches(cm)
            regChange(cm)
        }, true)
        option("lineSeparator", null, function (cm, val) {
            cm.doc.lineSep = val
            if (!val) { return }
            var newBreaks = [], lineNo = cm.doc.first
            cm.doc.iter(function (line) {
                for (var pos = 0;;) {
                    var found = line.text.indexOf(val, pos)
                    if (found == -1) { break }
                    pos = found + val.length
                    newBreaks.push(Pos(lineNo, found))
                }
                lineNo++
            })
            for (var i = newBreaks.length - 1; i >= 0; i--)
            { replaceRange(cm.doc, val, newBreaks[i], Pos(newBreaks[i].line, newBreaks[i].ch + val.length)) }
        })
        option("specialChars", /[\u0000-\u001f\u007f\u00ad\u200b-\u200f\u2028\u2029\ufeff]/g, function (cm, val, old) {
            cm.state.specialChars = new RegExp(val.source + (val.test("\t") ? "" : "|\t"), "g")
            if (old != Init) { cm.refresh() }
        })
        option("specialCharPlaceholder", defaultSpecialCharPlaceholder, function (cm) { return cm.refresh(); }, true)
        option("electricChars", true)
        option("inputStyle", mobile ? "contenteditable" : "textarea", function () {
            throw new Error("inputStyle can not (yet) be changed in a running editor") // FIXME
        }, true)
        option("spellcheck", false, function (cm, val) { return cm.getInputField().spellcheck = val; }, true)
        option("rtlMoveVisually", !windows)
        option("wholeLineUpdateBefore", true)

        option("theme", "default", function (cm) {
            themeChanged(cm)
            guttersChanged(cm)
        }, true)
        option("keyMap", "default", function (cm, val, old) {
            var next = getKeyMap(val)
            var prev = old != Init && getKeyMap(old)
            if (prev && prev.detach) { prev.detach(cm, next) }
            if (next.attach) { next.attach(cm, prev || null) }
        })
        option("extraKeys", null)

        option("lineWrapping", false, wrappingChanged, true)
        option("gutters", [], function (cm) {
            setGuttersForLineNumbers(cm.options)
            guttersChanged(cm)
        }, true)
        option("fixedGutter", true, function (cm, val) {
            cm.display.gutters.style.left = val ? compensateForHScroll(cm.display) + "px" : "0"
            cm.refresh()
        }, true)
        option("coverGutterNextToScrollbar", false, function (cm) { return updateScrollbars(cm); }, true)
        option("scrollbarStyle", "native", function (cm) {
            initScrollbars(cm)
            updateScrollbars(cm)
            cm.display.scrollbars.setScrollTop(cm.doc.scrollTop)
            cm.display.scrollbars.setScrollLeft(cm.doc.scrollLeft)
        }, true)
        option("lineNumbers", false, function (cm) {
            setGuttersForLineNumbers(cm.options)
            guttersChanged(cm)
        }, true)
        option("firstLineNumber", 1, guttersChanged, true)
        option("lineNumberFormatter", function (integer) { return integer; }, guttersChanged, true)
        option("showCursorWhenSelecting", false, updateSelection, true)

        option("resetSelectionOnContextMenu", true)
        option("lineWiseCopyCut", true)

        option("readOnly", false, function (cm, val) {
            if (val == "nocursor") {
                onBlur(cm)
                cm.display.input.blur()
                cm.display.disabled = true
            } else {
                cm.display.disabled = false
            }
            cm.display.input.readOnlyChanged(val)
        })
        option("disableInput", false, function (cm, val) {if (!val) { cm.display.input.reset() }}, true)
        option("dragDrop", true, dragDropChanged)
        option("allowDropFileTypes", null)

        option("cursorBlinkRate", 530)
        option("cursorScrollMargin", 0)
        option("cursorHeight", 1, updateSelection, true)
        option("singleCursorHeightPerLine", true, updateSelection, true)
        option("workTime", 100)
        option("workDelay", 100)
        option("flattenSpans", true, resetModeState, true)
        option("addModeClass", false, resetModeState, true)
        option("pollInterval", 100)
        option("undoDepth", 200, function (cm, val) { return cm.doc.history.undoDepth = val; })
        option("historyEventDelay", 1250)
        option("viewportMargin", 10, function (cm) { return cm.refresh(); }, true)
        option("maxHighlightLength", 10000, resetModeState, true)
        option("moveInputWithCursor", true, function (cm, val) {
            if (!val) { cm.display.input.resetPosition() }
        })

        option("tabindex", null, function (cm, val) { return cm.display.input.getField().tabIndex = val || ""; })
        option("autofocus", null)
    }

    function guttersChanged(cm) {
        updateGutters(cm)
        regChange(cm)
        setTimeout(function () { return alignHorizontally(cm); }, 20)
    }

    function dragDropChanged(cm, value, old) {
        var wasOn = old && old != Init
        if (!value != !wasOn) {
            var funcs = cm.display.dragFunctions
            var toggle = value ? on : off
            toggle(cm.display.scroller, "dragstart", funcs.start)
            toggle(cm.display.scroller, "dragenter", funcs.enter)
            toggle(cm.display.scroller, "dragover", funcs.over)
            toggle(cm.display.scroller, "dragleave", funcs.leave)
            toggle(cm.display.scroller, "drop", funcs.drop)
        }
    }

    function wrappingChanged(cm) {
        if (cm.options.lineWrapping) {
            addClass(cm.display.wrapper, "CodeMirror-wrap")
            cm.display.sizer.style.minWidth = ""
            cm.display.sizerWidth = null
        } else {
            rmClass(cm.display.wrapper, "CodeMirror-wrap")
            findMaxLine(cm)
        }
        estimateLineHeights(cm)
        regChange(cm)
        clearCaches(cm)
        setTimeout(function () { return updateScrollbars(cm); }, 100)
    }

// A CodeMirror instance represents an editor. This is the object
// that user code is usually dealing with.

    function CodeMirror$1(place, options) {
        var this$1 = this;

        if (!(this instanceof CodeMirror$1)) { return new CodeMirror$1(place, options) }

        this.options = options = options ? copyObj(options) : {}
        // Determine effective options based on given values and defaults.
        copyObj(defaults, options, false)
        setGuttersForLineNumbers(options)

        var doc = options.value
        if (typeof doc == "string") { doc = new Doc(doc, options.mode, null, options.lineSeparator) }
        this.doc = doc

        var input = new CodeMirror$1.inputStyles[options.inputStyle](this)
        var display = this.display = new Display(place, doc, input)
        display.wrapper.CodeMirror = this
        updateGutters(this)
        themeChanged(this)
        if (options.lineWrapping)
        { this.display.wrapper.className += " CodeMirror-wrap" }
        if (options.autofocus && !mobile) { display.input.focus() }
        initScrollbars(this)

        this.state = {
            keyMaps: [],  // stores maps added by addKeyMap
            overlays: [], // highlighting overlays, as added by addOverlay
            modeGen: 0,   // bumped when mode/overlay changes, used to invalidate highlighting info
            overwrite: false,
            delayingBlurEvent: false,
            focused: false,
            suppressEdits: false, // used to disable editing during key handlers when in readOnly mode
            pasteIncoming: false, cutIncoming: false, // help recognize paste/cut edits in input.poll
            selectingText: false,
            draggingText: false,
            highlight: new Delayed(), // stores highlight worker timeout
            keySeq: null,  // Unfinished key sequence
            specialChars: null
        }

        // Override magic textarea content restore that IE sometimes does
        // on our hidden textarea on reload
        if (ie && ie_version < 11) { setTimeout(function () { return this$1.display.input.reset(true); }, 20) }

        registerEventHandlers(this)
        ensureGlobalHandlers()

        startOperation(this)
        this.curOp.forceUpdate = true
        attachDoc(this, doc)

        if ((options.autofocus && !mobile) || this.hasFocus())
        { setTimeout(bind(onFocus, this), 20) }
        else
        { onBlur(this) }

        for (var opt in optionHandlers) { if (optionHandlers.hasOwnProperty(opt))
        { optionHandlers[opt](this$1, options[opt], Init) } }
        maybeUpdateLineNumberWidth(this)
        if (options.finishInit) { options.finishInit(this) }
        for (var i = 0; i < initHooks.length; ++i) { initHooks[i](this$1) }
        endOperation(this)
        // Suppress optimizelegibility in Webkit, since it breaks text
        // measuring on line wrapping boundaries.
        if (webkit && options.lineWrapping &&
            getComputedStyle(display.lineDiv).textRendering == "optimizelegibility")
        { display.lineDiv.style.textRendering = "auto" }
    }

// The default configuration options.
    CodeMirror$1.defaults = defaults
// Functions to run when options are changed.
    CodeMirror$1.optionHandlers = optionHandlers

// Attach the necessary event handlers when initializing the editor
    function registerEventHandlers(cm) {
        var d = cm.display
        on(d.scroller, "mousedown", operation(cm, onMouseDown))
        // Older IE's will not fire a second mousedown for a double click
        if (ie && ie_version < 11)
        { on(d.scroller, "dblclick", operation(cm, function (e) {
            if (signalDOMEvent(cm, e)) { return }
            var pos = posFromMouse(cm, e)
            if (!pos || clickInGutter(cm, e) || eventInWidget(cm.display, e)) { return }
            e_preventDefault(e)
            var word = cm.findWordAt(pos)
            extendSelection(cm.doc, word.anchor, word.head)
        })) }
        else
        { on(d.scroller, "dblclick", function (e) { return signalDOMEvent(cm, e) || e_preventDefault(e); }) }
        // Some browsers fire contextmenu *after* opening the menu, at
        // which point we can't mess with it anymore. Context menu is
        // handled in onMouseDown for these browsers.
        if (!captureRightClick) { on(d.scroller, "contextmenu", function (e) { return onContextMenu(cm, e); }) }

        // Used to suppress mouse event handling when a touch happens
        var touchFinished, prevTouch = {end: 0}
        function finishTouch() {
            if (d.activeTouch) {
                touchFinished = setTimeout(function () { return d.activeTouch = null; }, 1000)
                prevTouch = d.activeTouch
                prevTouch.end = +new Date
            }
        }
        function isMouseLikeTouchEvent(e) {
            if (e.touches.length != 1) { return false }
            var touch = e.touches[0]
            return touch.radiusX <= 1 && touch.radiusY <= 1
        }
        function farAway(touch, other) {
            if (other.left == null) { return true }
            var dx = other.left - touch.left, dy = other.top - touch.top
            return dx * dx + dy * dy > 20 * 20
        }
        on(d.scroller, "touchstart", function (e) {
            if (!signalDOMEvent(cm, e) && !isMouseLikeTouchEvent(e)) {
                clearTimeout(touchFinished)
                var now = +new Date
                d.activeTouch = {start: now, moved: false,
                    prev: now - prevTouch.end <= 300 ? prevTouch : null}
                if (e.touches.length == 1) {
                    d.activeTouch.left = e.touches[0].pageX
                    d.activeTouch.top = e.touches[0].pageY
                }
            }
        })
        on(d.scroller, "touchmove", function () {
            if (d.activeTouch) { d.activeTouch.moved = true }
        })
        on(d.scroller, "touchend", function (e) {
            var touch = d.activeTouch
            if (touch && !eventInWidget(d, e) && touch.left != null &&
                !touch.moved && new Date - touch.start < 300) {
                var pos = cm.coordsChar(d.activeTouch, "page"), range
                if (!touch.prev || farAway(touch, touch.prev)) // Single tap
                { range = new Range(pos, pos) }
                else if (!touch.prev.prev || farAway(touch, touch.prev.prev)) // Double tap
                { range = cm.findWordAt(pos) }
                else // Triple tap
                { range = new Range(Pos(pos.line, 0), clipPos(cm.doc, Pos(pos.line + 1, 0))) }
                cm.setSelection(range.anchor, range.head)
                cm.focus()
                e_preventDefault(e)
            }
            finishTouch()
        })
        on(d.scroller, "touchcancel", finishTouch)

        // Sync scrolling between fake scrollbars and real scrollable
        // area, ensure viewport is updated when scrolling.
        on(d.scroller, "scroll", function () {
            if (d.scroller.clientHeight) {
                setScrollTop(cm, d.scroller.scrollTop)
                setScrollLeft(cm, d.scroller.scrollLeft, true)
                signal(cm, "scroll", cm)
            }
        })

        // Listen to wheel events in order to try and update the viewport on time.
        on(d.scroller, "mousewheel", function (e) { return onScrollWheel(cm, e); })
        on(d.scroller, "DOMMouseScroll", function (e) { return onScrollWheel(cm, e); })

        // Prevent wrapper from ever scrolling
        on(d.wrapper, "scroll", function () { return d.wrapper.scrollTop = d.wrapper.scrollLeft = 0; })

        d.dragFunctions = {
            enter: function (e) {if (!signalDOMEvent(cm, e)) { e_stop(e) }},
            over: function (e) {if (!signalDOMEvent(cm, e)) { onDragOver(cm, e); e_stop(e) }},
            start: function (e) { return onDragStart(cm, e); },
            drop: operation(cm, onDrop),
            leave: function (e) {if (!signalDOMEvent(cm, e)) { clearDragCursor(cm) }}
        }

        var inp = d.input.getField()
        on(inp, "keyup", function (e) { return onKeyUp.call(cm, e); })
        on(inp, "keydown", operation(cm, onKeyDown))
        on(inp, "keypress", operation(cm, onKeyPress))
        on(inp, "focus", function (e) { return onFocus(cm, e); })
        on(inp, "blur", function (e) { return onBlur(cm, e); })
    }

    var initHooks = []
    CodeMirror$1.defineInitHook = function (f) { return initHooks.push(f); }

// Indent the given line. The how parameter can be "smart",
// "add"/null, "subtract", or "prev". When aggressive is false
// (typically set to true for forced single-line indents), empty
// lines are not indented, and places where the mode returns Pass
// are left alone.
    function indentLine(cm, n, how, aggressive) {
        var doc = cm.doc, state
        if (how == null) { how = "add" }
        if (how == "smart") {
            // Fall back to "prev" when the mode doesn't have an indentation
            // method.
            if (!doc.mode.indent) { how = "prev" }
            else { state = getStateBefore(cm, n) }
        }

        var tabSize = cm.options.tabSize
        var line = getLine(doc, n), curSpace = countColumn(line.text, null, tabSize)
        if (line.stateAfter) { line.stateAfter = null }
        var curSpaceString = line.text.match(/^\s*/)[0], indentation
        if (!aggressive && !/\S/.test(line.text)) {
            indentation = 0
            how = "not"
        } else if (how == "smart") {
            indentation = doc.mode.indent(state, line.text.slice(curSpaceString.length), line.text)
            if (indentation == Pass || indentation > 150) {
                if (!aggressive) { return }
                how = "prev"
            }
        }
        if (how == "prev") {
            if (n > doc.first) { indentation = countColumn(getLine(doc, n-1).text, null, tabSize) }
            else { indentation = 0 }
        } else if (how == "add") {
            indentation = curSpace + cm.options.indentUnit
        } else if (how == "subtract") {
            indentation = curSpace - cm.options.indentUnit
        } else if (typeof how == "number") {
            indentation = curSpace + how
        }
        indentation = Math.max(0, indentation)

        var indentString = "", pos = 0
        if (cm.options.indentWithTabs)
        { for (var i = Math.floor(indentation / tabSize); i; --i) {pos += tabSize; indentString += "\t"} }
        if (pos < indentation) { indentString += spaceStr(indentation - pos) }

        if (indentString != curSpaceString) {
            replaceRange(doc, indentString, Pos(n, 0), Pos(n, curSpaceString.length), "+input")
            line.stateAfter = null
            return true
        } else {
            // Ensure that, if the cursor was in the whitespace at the start
            // of the line, it is moved to the end of that space.
            for (var i$1 = 0; i$1 < doc.sel.ranges.length; i$1++) {
                var range = doc.sel.ranges[i$1]
                if (range.head.line == n && range.head.ch < curSpaceString.length) {
                    var pos$1 = Pos(n, curSpaceString.length)
                    replaceOneSelection(doc, i$1, new Range(pos$1, pos$1))
                    break
                }
            }
        }
    }

// This will be set to a {lineWise: bool, text: [string]} object, so
// that, when pasting, we know what kind of selections the copied
// text was made out of.
    var lastCopied = null

    function setLastCopied(newLastCopied) {
        lastCopied = newLastCopied
    }

    function applyTextInput(cm, inserted, deleted, sel, origin) {
        var doc = cm.doc
        cm.display.shift = false
        if (!sel) { sel = doc.sel }

        var paste = cm.state.pasteIncoming || origin == "paste"
        var textLines = doc.splitLines(inserted), multiPaste = null
        // When pasing N lines into N selections, insert one line per selection
        if (paste && sel.ranges.length > 1) {
            if (lastCopied && lastCopied.text.join("\n") == inserted) {
                if (sel.ranges.length % lastCopied.text.length == 0) {
                    multiPaste = []
                    for (var i = 0; i < lastCopied.text.length; i++)
                    { multiPaste.push(doc.splitLines(lastCopied.text[i])) }
                }
            } else if (textLines.length == sel.ranges.length) {
                multiPaste = map(textLines, function (l) { return [l]; })
            }
        }

        var updateInput
        // Normal behavior is to insert the new text into every selection
        for (var i$1 = sel.ranges.length - 1; i$1 >= 0; i$1--) {
            var range$$1 = sel.ranges[i$1]
            var from = range$$1.from(), to = range$$1.to()
            if (range$$1.empty()) {
                if (deleted && deleted > 0) // Handle deletion
                { from = Pos(from.line, from.ch - deleted) }
                else if (cm.state.overwrite && !paste) // Handle overwrite
                { to = Pos(to.line, Math.min(getLine(doc, to.line).text.length, to.ch + lst(textLines).length)) }
                else if (lastCopied && lastCopied.lineWise && lastCopied.text.join("\n") == inserted)
                { from = to = Pos(from.line, 0) }
            }
            updateInput = cm.curOp.updateInput
            var changeEvent = {from: from, to: to, text: multiPaste ? multiPaste[i$1 % multiPaste.length] : textLines,
                origin: origin || (paste ? "paste" : cm.state.cutIncoming ? "cut" : "+input")}
            makeChange(cm.doc, changeEvent)
            signalLater(cm, "inputRead", cm, changeEvent)
        }
        if (inserted && !paste)
        { triggerElectric(cm, inserted) }

        ensureCursorVisible(cm)
        cm.curOp.updateInput = updateInput
        cm.curOp.typing = true
        cm.state.pasteIncoming = cm.state.cutIncoming = false
    }

    function handlePaste(e, cm) {
        var pasted = e.clipboardData && e.clipboardData.getData("Text")
        if (pasted) {
            e.preventDefault()
            if (!cm.isReadOnly() && !cm.options.disableInput)
            { runInOp(cm, function () { return applyTextInput(cm, pasted, 0, null, "paste"); }) }
            return true
        }
    }

    function triggerElectric(cm, inserted) {
        // When an 'electric' character is inserted, immediately trigger a reindent
        if (!cm.options.electricChars || !cm.options.smartIndent) { return }
        var sel = cm.doc.sel

        for (var i = sel.ranges.length - 1; i >= 0; i--) {
            var range$$1 = sel.ranges[i]
            if (range$$1.head.ch > 100 || (i && sel.ranges[i - 1].head.line == range$$1.head.line)) { continue }
            var mode = cm.getModeAt(range$$1.head)
            var indented = false
            if (mode.electricChars) {
                for (var j = 0; j < mode.electricChars.length; j++)
                { if (inserted.indexOf(mode.electricChars.charAt(j)) > -1) {
                    indented = indentLine(cm, range$$1.head.line, "smart")
                    break
                } }
            } else if (mode.electricInput) {
                if (mode.electricInput.test(getLine(cm.doc, range$$1.head.line).text.slice(0, range$$1.head.ch)))
                { indented = indentLine(cm, range$$1.head.line, "smart") }
            }
            if (indented) { signalLater(cm, "electricInput", cm, range$$1.head.line) }
        }
    }

    function copyableRanges(cm) {
        var text = [], ranges = []
        for (var i = 0; i < cm.doc.sel.ranges.length; i++) {
            var line = cm.doc.sel.ranges[i].head.line
            var lineRange = {anchor: Pos(line, 0), head: Pos(line + 1, 0)}
            ranges.push(lineRange)
            text.push(cm.getRange(lineRange.anchor, lineRange.head))
        }
        return {text: text, ranges: ranges}
    }

    function disableBrowserMagic(field, spellcheck) {
        field.setAttribute("autocorrect", "off")
        field.setAttribute("autocapitalize", "off")
        field.setAttribute("spellcheck", !!spellcheck)
    }

    function hiddenTextarea() {
        var te = elt("textarea", null, null, "position: absolute; bottom: -1em; padding: 0; width: 1px; height: 1em; outline: none")
        var div = elt("div", [te], null, "overflow: hidden; position: relative; width: 3px; height: 0px;")
        // The textarea is kept positioned near the cursor to prevent the
        // fact that it'll be scrolled into view on input from scrolling
        // our fake cursor out of view. On webkit, when wrap=off, paste is
        // very slow. So make the area wide instead.
        if (webkit) { te.style.width = "1000px" }
        else { te.setAttribute("wrap", "off") }
        // If border: 0; -- iOS fails to open keyboard (issue #1287)
        if (ios) { te.style.border = "1px solid black" }
        disableBrowserMagic(te)
        return div
    }

// The publicly visible API. Note that methodOp(f) means
// 'wrap f in an operation, performed on its `this` parameter'.

// This is not the complete set of editor methods. Most of the
// methods defined on the Doc type are also injected into
// CodeMirror.prototype, for backwards compatibility and
// convenience.

    var addEditorMethods = function(CodeMirror) {
        var optionHandlers = CodeMirror.optionHandlers

        var helpers = CodeMirror.helpers = {}

        CodeMirror.prototype = {
            constructor: CodeMirror,
            focus: function(){window.focus(); this.display.input.focus()},

            setOption: function(option, value) {
                var options = this.options, old = options[option]
                if (options[option] == value && option != "mode") { return }
                options[option] = value
                if (optionHandlers.hasOwnProperty(option))
                { operation(this, optionHandlers[option])(this, value, old) }
            },

            getOption: function(option) {return this.options[option]},
            getDoc: function() {return this.doc},

            addKeyMap: function(map$$1, bottom) {
                this.state.keyMaps[bottom ? "push" : "unshift"](getKeyMap(map$$1))
            },
            removeKeyMap: function(map$$1) {
                var maps = this.state.keyMaps
                for (var i = 0; i < maps.length; ++i)
                { if (maps[i] == map$$1 || maps[i].name == map$$1) {
                    maps.splice(i, 1)
                    return true
                } }
            },

            addOverlay: methodOp(function(spec, options) {
                var mode = spec.token ? spec : CodeMirror.getMode(this.options, spec)
                if (mode.startState) { throw new Error("Overlays may not be stateful.") }
                insertSorted(this.state.overlays,
                    {mode: mode, modeSpec: spec, opaque: options && options.opaque,
                        priority: (options && options.priority) || 0},
                    function (overlay) { return overlay.priority; })
                this.state.modeGen++
                regChange(this)
            }),
            removeOverlay: methodOp(function(spec) {
                var this$1 = this;

                var overlays = this.state.overlays
                for (var i = 0; i < overlays.length; ++i) {
                    var cur = overlays[i].modeSpec
                    if (cur == spec || typeof spec == "string" && cur.name == spec) {
                        overlays.splice(i, 1)
                        this$1.state.modeGen++
                        regChange(this$1)
                        return
                    }
                }
            }),

            indentLine: methodOp(function(n, dir, aggressive) {
                if (typeof dir != "string" && typeof dir != "number") {
                    if (dir == null) { dir = this.options.smartIndent ? "smart" : "prev" }
                    else { dir = dir ? "add" : "subtract" }
                }
                if (isLine(this.doc, n)) { indentLine(this, n, dir, aggressive) }
            }),
            indentSelection: methodOp(function(how) {
                var this$1 = this;

                var ranges = this.doc.sel.ranges, end = -1
                for (var i = 0; i < ranges.length; i++) {
                    var range$$1 = ranges[i]
                    if (!range$$1.empty()) {
                        var from = range$$1.from(), to = range$$1.to()
                        var start = Math.max(end, from.line)
                        end = Math.min(this$1.lastLine(), to.line - (to.ch ? 0 : 1)) + 1
                        for (var j = start; j < end; ++j)
                        { indentLine(this$1, j, how) }
                        var newRanges = this$1.doc.sel.ranges
                        if (from.ch == 0 && ranges.length == newRanges.length && newRanges[i].from().ch > 0)
                        { replaceOneSelection(this$1.doc, i, new Range(from, newRanges[i].to()), sel_dontScroll) }
                    } else if (range$$1.head.line > end) {
                        indentLine(this$1, range$$1.head.line, how, true)
                        end = range$$1.head.line
                        if (i == this$1.doc.sel.primIndex) { ensureCursorVisible(this$1) }
                    }
                }
            }),

            // Fetch the parser token for a given character. Useful for hacks
            // that want to inspect the mode state (say, for completion).
            getTokenAt: function(pos, precise) {
                return takeToken(this, pos, precise)
            },

            getLineTokens: function(line, precise) {
                return takeToken(this, Pos(line), precise, true)
            },

            getTokenTypeAt: function(pos) {
                pos = clipPos(this.doc, pos)
                var styles = getLineStyles(this, getLine(this.doc, pos.line))
                var before = 0, after = (styles.length - 1) / 2, ch = pos.ch
                var type
                if (ch == 0) { type = styles[2] }
                else { for (;;) {
                    var mid = (before + after) >> 1
                    if ((mid ? styles[mid * 2 - 1] : 0) >= ch) { after = mid }
                    else if (styles[mid * 2 + 1] < ch) { before = mid + 1 }
                    else { type = styles[mid * 2 + 2]; break }
                } }
                var cut = type ? type.indexOf("overlay ") : -1
                return cut < 0 ? type : cut == 0 ? null : type.slice(0, cut - 1)
            },

            getModeAt: function(pos) {
                var mode = this.doc.mode
                if (!mode.innerMode) { return mode }
                return CodeMirror.innerMode(mode, this.getTokenAt(pos).state).mode
            },

            getHelper: function(pos, type) {
                return this.getHelpers(pos, type)[0]
            },

            getHelpers: function(pos, type) {
                var this$1 = this;

                var found = []
                if (!helpers.hasOwnProperty(type)) { return found }
                var help = helpers[type], mode = this.getModeAt(pos)
                if (typeof mode[type] == "string") {
                    if (help[mode[type]]) { found.push(help[mode[type]]) }
                } else if (mode[type]) {
                    for (var i = 0; i < mode[type].length; i++) {
                        var val = help[mode[type][i]]
                        if (val) { found.push(val) }
                    }
                } else if (mode.helperType && help[mode.helperType]) {
                    found.push(help[mode.helperType])
                } else if (help[mode.name]) {
                    found.push(help[mode.name])
                }
                for (var i$1 = 0; i$1 < help._global.length; i$1++) {
                    var cur = help._global[i$1]
                    if (cur.pred(mode, this$1) && indexOf(found, cur.val) == -1)
                    { found.push(cur.val) }
                }
                return found
            },

            getStateAfter: function(line, precise) {
                var doc = this.doc
                line = clipLine(doc, line == null ? doc.first + doc.size - 1: line)
                return getStateBefore(this, line + 1, precise)
            },

            cursorCoords: function(start, mode) {
                var pos, range$$1 = this.doc.sel.primary()
                if (start == null) { pos = range$$1.head }
                else if (typeof start == "object") { pos = clipPos(this.doc, start) }
                else { pos = start ? range$$1.from() : range$$1.to() }
                return cursorCoords(this, pos, mode || "page")
            },

            charCoords: function(pos, mode) {
                return charCoords(this, clipPos(this.doc, pos), mode || "page")
            },

            coordsChar: function(coords, mode) {
                coords = fromCoordSystem(this, coords, mode || "page")
                return coordsChar(this, coords.left, coords.top)
            },

            lineAtHeight: function(height, mode) {
                height = fromCoordSystem(this, {top: height, left: 0}, mode || "page").top
                return lineAtHeight(this.doc, height + this.display.viewOffset)
            },
            heightAtLine: function(line, mode) {
                var end = false, lineObj
                if (typeof line == "number") {
                    var last = this.doc.first + this.doc.size - 1
                    if (line < this.doc.first) { line = this.doc.first }
                    else if (line > last) { line = last; end = true }
                    lineObj = getLine(this.doc, line)
                } else {
                    lineObj = line
                }
                return intoCoordSystem(this, lineObj, {top: 0, left: 0}, mode || "page").top +
                    (end ? this.doc.height - heightAtLine(lineObj) : 0)
            },

            defaultTextHeight: function() { return textHeight(this.display) },
            defaultCharWidth: function() { return charWidth(this.display) },

            setGutterMarker: methodOp(function(line, gutterID, value) {
                return changeLine(this.doc, line, "gutter", function (line) {
                    var markers = line.gutterMarkers || (line.gutterMarkers = {})
                    markers[gutterID] = value
                    if (!value && isEmpty(markers)) { line.gutterMarkers = null }
                    return true
                })
            }),

            clearGutter: methodOp(function(gutterID) {
                var this$1 = this;

                var doc = this.doc, i = doc.first
                doc.iter(function (line) {
                    if (line.gutterMarkers && line.gutterMarkers[gutterID]) {
                        line.gutterMarkers[gutterID] = null
                        regLineChange(this$1, i, "gutter")
                        if (isEmpty(line.gutterMarkers)) { line.gutterMarkers = null }
                    }
                    ++i
                })
            }),

            lineInfo: function(line) {
                var n
                if (typeof line == "number") {
                    if (!isLine(this.doc, line)) { return null }
                    n = line
                    line = getLine(this.doc, line)
                    if (!line) { return null }
                } else {
                    n = lineNo(line)
                    if (n == null) { return null }
                }
                return {line: n, handle: line, text: line.text, gutterMarkers: line.gutterMarkers,
                    textClass: line.textClass, bgClass: line.bgClass, wrapClass: line.wrapClass,
                    widgets: line.widgets}
            },

            getViewport: function() { return {from: this.display.viewFrom, to: this.display.viewTo}},

            addWidget: function(pos, node, scroll, vert, horiz) {
                var display = this.display
                pos = cursorCoords(this, clipPos(this.doc, pos))
                var top = pos.bottom, left = pos.left
                node.style.position = "absolute"
                node.setAttribute("cm-ignore-events", "true")
                this.display.input.setUneditable(node)
                display.sizer.appendChild(node)
                if (vert == "over") {
                    top = pos.top
                } else if (vert == "above" || vert == "near") {
                    var vspace = Math.max(display.wrapper.clientHeight, this.doc.height),
                        hspace = Math.max(display.sizer.clientWidth, display.lineSpace.clientWidth)
                    // Default to positioning above (if specified and possible); otherwise default to positioning below
                    if ((vert == 'above' || pos.bottom + node.offsetHeight > vspace) && pos.top > node.offsetHeight)
                    { top = pos.top - node.offsetHeight }
                    else if (pos.bottom + node.offsetHeight <= vspace)
                    { top = pos.bottom }
                    if (left + node.offsetWidth > hspace)
                    { left = hspace - node.offsetWidth }
                }
                node.style.top = top + "px"
                node.style.left = node.style.right = ""
                if (horiz == "right") {
                    left = display.sizer.clientWidth - node.offsetWidth
                    node.style.right = "0px"
                } else {
                    if (horiz == "left") { left = 0 }
                    else if (horiz == "middle") { left = (display.sizer.clientWidth - node.offsetWidth) / 2 }
                    node.style.left = left + "px"
                }
                if (scroll)
                { scrollIntoView(this, left, top, left + node.offsetWidth, top + node.offsetHeight) }
            },

            triggerOnKeyDown: methodOp(onKeyDown),
            triggerOnKeyPress: methodOp(onKeyPress),
            triggerOnKeyUp: onKeyUp,

            execCommand: function(cmd) {
                if (commands.hasOwnProperty(cmd))
                { return commands[cmd].call(null, this) }
            },

            triggerElectric: methodOp(function(text) { triggerElectric(this, text) }),

            findPosH: function(from, amount, unit, visually) {
                var this$1 = this;

                var dir = 1
                if (amount < 0) { dir = -1; amount = -amount }
                var cur = clipPos(this.doc, from)
                for (var i = 0; i < amount; ++i) {
                    cur = findPosH(this$1.doc, cur, dir, unit, visually)
                    if (cur.hitSide) { break }
                }
                return cur
            },

            moveH: methodOp(function(dir, unit) {
                var this$1 = this;

                this.extendSelectionsBy(function (range$$1) {
                    if (this$1.display.shift || this$1.doc.extend || range$$1.empty())
                    { return findPosH(this$1.doc, range$$1.head, dir, unit, this$1.options.rtlMoveVisually) }
                    else
                    { return dir < 0 ? range$$1.from() : range$$1.to() }
                }, sel_move)
            }),

            deleteH: methodOp(function(dir, unit) {
                var sel = this.doc.sel, doc = this.doc
                if (sel.somethingSelected())
                { doc.replaceSelection("", null, "+delete") }
                else
                { deleteNearSelection(this, function (range$$1) {
                    var other = findPosH(doc, range$$1.head, dir, unit, false)
                    return dir < 0 ? {from: other, to: range$$1.head} : {from: range$$1.head, to: other}
                }) }
            }),

            findPosV: function(from, amount, unit, goalColumn) {
                var this$1 = this;

                var dir = 1, x = goalColumn
                if (amount < 0) { dir = -1; amount = -amount }
                var cur = clipPos(this.doc, from)
                for (var i = 0; i < amount; ++i) {
                    var coords = cursorCoords(this$1, cur, "div")
                    if (x == null) { x = coords.left }
                    else { coords.left = x }
                    cur = findPosV(this$1, coords, dir, unit)
                    if (cur.hitSide) { break }
                }
                return cur
            },

            moveV: methodOp(function(dir, unit) {
                var this$1 = this;

                var doc = this.doc, goals = []
                var collapse = !this.display.shift && !doc.extend && doc.sel.somethingSelected()
                doc.extendSelectionsBy(function (range$$1) {
                    if (collapse)
                    { return dir < 0 ? range$$1.from() : range$$1.to() }
                    var headPos = cursorCoords(this$1, range$$1.head, "div")
                    if (range$$1.goalColumn != null) { headPos.left = range$$1.goalColumn }
                    goals.push(headPos.left)
                    var pos = findPosV(this$1, headPos, dir, unit)
                    if (unit == "page" && range$$1 == doc.sel.primary())
                    { addToScrollPos(this$1, null, charCoords(this$1, pos, "div").top - headPos.top) }
                    return pos
                }, sel_move)
                if (goals.length) { for (var i = 0; i < doc.sel.ranges.length; i++)
                { doc.sel.ranges[i].goalColumn = goals[i] } }
            }),

            // Find the word at the given position (as returned by coordsChar).
            findWordAt: function(pos) {
                var doc = this.doc, line = getLine(doc, pos.line).text
                var start = pos.ch, end = pos.ch
                if (line) {
                    var helper = this.getHelper(pos, "wordChars")
                    if ((pos.xRel < 0 || end == line.length) && start) { --start; } else { ++end }
                    var startChar = line.charAt(start)
                    var check = isWordChar(startChar, helper)
                        ? function (ch) { return isWordChar(ch, helper); }
                        : /\s/.test(startChar) ? function (ch) { return /\s/.test(ch); }
                        : function (ch) { return (!/\s/.test(ch) && !isWordChar(ch)); }
                    while (start > 0 && check(line.charAt(start - 1))) { --start }
                    while (end < line.length && check(line.charAt(end))) { ++end }
                }
                return new Range(Pos(pos.line, start), Pos(pos.line, end))
            },

            toggleOverwrite: function(value) {
                if (value != null && value == this.state.overwrite) { return }
                if (this.state.overwrite = !this.state.overwrite)
                { addClass(this.display.cursorDiv, "CodeMirror-overwrite") }
                else
                { rmClass(this.display.cursorDiv, "CodeMirror-overwrite") }

                signal(this, "overwriteToggle", this, this.state.overwrite)
            },
            hasFocus: function() { return this.display.input.getField() == activeElt() },
            isReadOnly: function() { return !!(this.options.readOnly || this.doc.cantEdit) },

            scrollTo: methodOp(function(x, y) {
                if (x != null || y != null) { resolveScrollToPos(this) }
                if (x != null) { this.curOp.scrollLeft = x }
                if (y != null) { this.curOp.scrollTop = y }
            }),
            getScrollInfo: function() {
                var scroller = this.display.scroller
                return {left: scroller.scrollLeft, top: scroller.scrollTop,
                    height: scroller.scrollHeight - scrollGap(this) - this.display.barHeight,
                    width: scroller.scrollWidth - scrollGap(this) - this.display.barWidth,
                    clientHeight: displayHeight(this), clientWidth: displayWidth(this)}
            },

            scrollIntoView: methodOp(function(range$$1, margin) {
                if (range$$1 == null) {
                    range$$1 = {from: this.doc.sel.primary().head, to: null}
                    if (margin == null) { margin = this.options.cursorScrollMargin }
                } else if (typeof range$$1 == "number") {
                    range$$1 = {from: Pos(range$$1, 0), to: null}
                } else if (range$$1.from == null) {
                    range$$1 = {from: range$$1, to: null}
                }
                if (!range$$1.to) { range$$1.to = range$$1.from }
                range$$1.margin = margin || 0

                if (range$$1.from.line != null) {
                    resolveScrollToPos(this)
                    this.curOp.scrollToPos = range$$1
                } else {
                    var sPos = calculateScrollPos(this, Math.min(range$$1.from.left, range$$1.to.left),
                        Math.min(range$$1.from.top, range$$1.to.top) - range$$1.margin,
                        Math.max(range$$1.from.right, range$$1.to.right),
                        Math.max(range$$1.from.bottom, range$$1.to.bottom) + range$$1.margin)
                    this.scrollTo(sPos.scrollLeft, sPos.scrollTop)
                }
            }),

            setSize: methodOp(function(width, height) {
                var this$1 = this;

                var interpret = function (val) { return typeof val == "number" || /^\d+$/.test(String(val)) ? val + "px" : val; }
                if (width != null) { this.display.wrapper.style.width = interpret(width) }
                if (height != null) { this.display.wrapper.style.height = interpret(height) }
                if (this.options.lineWrapping) { clearLineMeasurementCache(this) }
                var lineNo$$1 = this.display.viewFrom
                this.doc.iter(lineNo$$1, this.display.viewTo, function (line) {
                    if (line.widgets) { for (var i = 0; i < line.widgets.length; i++)
                    { if (line.widgets[i].noHScroll) { regLineChange(this$1, lineNo$$1, "widget"); break } } }
                    ++lineNo$$1
                })
                this.curOp.forceUpdate = true
                signal(this, "refresh", this)
            }),

            operation: function(f){return runInOp(this, f)},

            refresh: methodOp(function() {
                var oldHeight = this.display.cachedTextHeight
                regChange(this)
                this.curOp.forceUpdate = true
                clearCaches(this)
                this.scrollTo(this.doc.scrollLeft, this.doc.scrollTop)
                updateGutterSpace(this)
                if (oldHeight == null || Math.abs(oldHeight - textHeight(this.display)) > .5)
                { estimateLineHeights(this) }
                signal(this, "refresh", this)
            }),

            swapDoc: methodOp(function(doc) {
                var old = this.doc
                old.cm = null
                attachDoc(this, doc)
                clearCaches(this)
                this.display.input.reset()
                this.scrollTo(doc.scrollLeft, doc.scrollTop)
                this.curOp.forceScroll = true
                signalLater(this, "swapDoc", this, old)
                return old
            }),

            getInputField: function(){return this.display.input.getField()},
            getWrapperElement: function(){return this.display.wrapper},
            getScrollerElement: function(){return this.display.scroller},
            getGutterElement: function(){return this.display.gutters}
        }
        eventMixin(CodeMirror)

        CodeMirror.registerHelper = function(type, name, value) {
            if (!helpers.hasOwnProperty(type)) { helpers[type] = CodeMirror[type] = {_global: []} }
            helpers[type][name] = value
        }
        CodeMirror.registerGlobalHelper = function(type, name, predicate, value) {
            CodeMirror.registerHelper(type, name, value)
            helpers[type]._global.push({pred: predicate, val: value})
        }
    }

// Used for horizontal relative motion. Dir is -1 or 1 (left or
// right), unit can be "char", "column" (like char, but doesn't
// cross line boundaries), "word" (across next word), or "group" (to
// the start of next group of word or non-word-non-whitespace
// chars). The visually param controls whether, in right-to-left
// text, direction 1 means to move towards the next index in the
// string, or towards the character to the right of the current
// position. The resulting position will have a hitSide=true
// property if it reached the end of the document.
    function findPosH(doc, pos, dir, unit, visually) {
        var line = pos.line, ch = pos.ch, origDir = dir
        var lineObj = getLine(doc, line)
        function findNextLine() {
            var l = line + dir
            if (l < doc.first || l >= doc.first + doc.size) { return false }
            line = l
            return lineObj = getLine(doc, l)
        }
        function moveOnce(boundToLine) {
            var next = (visually ? moveVisually : moveLogically)(lineObj, ch, dir, true)
            if (next == null) {
                if (!boundToLine && findNextLine()) {
                    if (visually) { ch = (dir < 0 ? lineRight : lineLeft)(lineObj) }
                    else { ch = dir < 0 ? lineObj.text.length : 0 }
                } else { return false }
            } else { ch = next }
            return true
        }

        if (unit == "char") {
            moveOnce()
        } else if (unit == "column") {
            moveOnce(true)
        } else if (unit == "word" || unit == "group") {
            var sawType = null, group = unit == "group"
            var helper = doc.cm && doc.cm.getHelper(pos, "wordChars")
            for (var first = true;; first = false) {
                if (dir < 0 && !moveOnce(!first)) { break }
                var cur = lineObj.text.charAt(ch) || "\n"
                var type = isWordChar(cur, helper) ? "w"
                    : group && cur == "\n" ? "n"
                    : !group || /\s/.test(cur) ? null
                    : "p"
                if (group && !first && !type) { type = "s" }
                if (sawType && sawType != type) {
                    if (dir < 0) {dir = 1; moveOnce()}
                    break
                }

                if (type) { sawType = type }
                if (dir > 0 && !moveOnce(!first)) { break }
            }
        }
        var result = skipAtomic(doc, Pos(line, ch), pos, origDir, true)
        if (!cmp(pos, result)) { result.hitSide = true }
        return result
    }

// For relative vertical movement. Dir may be -1 or 1. Unit can be
// "page" or "line". The resulting position will have a hitSide=true
// property if it reached the end of the document.
    function findPosV(cm, pos, dir, unit) {
        var doc = cm.doc, x = pos.left, y
        if (unit == "page") {
            var pageSize = Math.min(cm.display.wrapper.clientHeight, window.innerHeight || document.documentElement.clientHeight)
            var moveAmount = Math.max(pageSize - .5 * textHeight(cm.display), 3)
            y = (dir > 0 ? pos.bottom : pos.top) + dir * moveAmount

        } else if (unit == "line") {
            y = dir > 0 ? pos.bottom + 3 : pos.top - 3
        }
        var target
        for (;;) {
            target = coordsChar(cm, x, y)
            if (!target.outside) { break }
            if (dir < 0 ? y <= 0 : y >= doc.height) { target.hitSide = true; break }
            y += dir * 5
        }
        return target
    }

// CONTENTEDITABLE INPUT STYLE

    function ContentEditableInput(cm) {
        this.cm = cm
        this.lastAnchorNode = this.lastAnchorOffset = this.lastFocusNode = this.lastFocusOffset = null
        this.polling = new Delayed()
        this.gracePeriod = false
    }

    ContentEditableInput.prototype = copyObj({
        init: function(display) {
            var input = this, cm = input.cm
            var div = input.div = display.lineDiv
            disableBrowserMagic(div, cm.options.spellcheck)

            on(div, "paste", function (e) {
                if (signalDOMEvent(cm, e) || handlePaste(e, cm)) { return }
                // IE doesn't fire input events, so we schedule a read for the pasted content in this way
                if (ie_version <= 11) { setTimeout(operation(cm, function () {
                    if (!input.pollContent()) { regChange(cm) }
                }), 20) }
            })

            on(div, "compositionstart", function (e) {
                var data = e.data
                input.composing = {sel: cm.doc.sel, data: data, startData: data}
                if (!data) { return }
                var prim = cm.doc.sel.primary()
                var line = cm.getLine(prim.head.line)
                var found = line.indexOf(data, Math.max(0, prim.head.ch - data.length))
                if (found > -1 && found <= prim.head.ch)
                { input.composing.sel = simpleSelection(Pos(prim.head.line, found),
                    Pos(prim.head.line, found + data.length)) }
            })
            on(div, "compositionupdate", function (e) { return input.composing.data = e.data; })
            on(div, "compositionend", function (e) {
                var ours = input.composing
                if (!ours) { return }
                if (e.data != ours.startData && !/\u200b/.test(e.data))
                { ours.data = e.data }
                // Need a small delay to prevent other code (input event,
                // selection polling) from doing damage when fired right after
                // compositionend.
                setTimeout(function () {
                    if (!ours.handled)
                    { input.applyComposition(ours) }
                    if (input.composing == ours)
                    { input.composing = null }
                }, 50)
            })

            on(div, "touchstart", function () { return input.forceCompositionEnd(); })

            on(div, "input", function () {
                if (input.composing) { return }
                if (cm.isReadOnly() || !input.pollContent())
                { runInOp(input.cm, function () { return regChange(cm); }) }
            })

            function onCopyCut(e) {
                if (signalDOMEvent(cm, e)) { return }
                if (cm.somethingSelected()) {
                    setLastCopied({lineWise: false, text: cm.getSelections()})
                    if (e.type == "cut") { cm.replaceSelection("", null, "cut") }
                } else if (!cm.options.lineWiseCopyCut) {
                    return
                } else {
                    var ranges = copyableRanges(cm)
                    setLastCopied({lineWise: true, text: ranges.text})
                    if (e.type == "cut") {
                        cm.operation(function () {
                            cm.setSelections(ranges.ranges, 0, sel_dontScroll)
                            cm.replaceSelection("", null, "cut")
                        })
                    }
                }
                if (e.clipboardData) {
                    e.clipboardData.clearData()
                    var content = lastCopied.text.join("\n")
                    // iOS exposes the clipboard API, but seems to discard content inserted into it
                    e.clipboardData.setData("Text", content)
                    if (e.clipboardData.getData("Text") == content) {
                        e.preventDefault()
                        return
                    }
                }
                // Old-fashioned briefly-focus-a-textarea hack
                var kludge = hiddenTextarea(), te = kludge.firstChild
                cm.display.lineSpace.insertBefore(kludge, cm.display.lineSpace.firstChild)
                te.value = lastCopied.text.join("\n")
                var hadFocus = document.activeElement
                selectInput(te)
                setTimeout(function () {
                    cm.display.lineSpace.removeChild(kludge)
                    hadFocus.focus()
                    if (hadFocus == div) { input.showPrimarySelection() }
                }, 50)
            }
            on(div, "copy", onCopyCut)
            on(div, "cut", onCopyCut)
        },

        prepareSelection: function() {
            var result = prepareSelection(this.cm, false)
            result.focus = this.cm.state.focused
            return result
        },

        showSelection: function(info, takeFocus) {
            if (!info || !this.cm.display.view.length) { return }
            if (info.focus || takeFocus) { this.showPrimarySelection() }
            this.showMultipleSelections(info)
        },

        showPrimarySelection: function() {
            var sel = window.getSelection(), prim = this.cm.doc.sel.primary()
            var curAnchor = domToPos(this.cm, sel.anchorNode, sel.anchorOffset)
            var curFocus = domToPos(this.cm, sel.focusNode, sel.focusOffset)
            if (curAnchor && !curAnchor.bad && curFocus && !curFocus.bad &&
                cmp(minPos(curAnchor, curFocus), prim.from()) == 0 &&
                cmp(maxPos(curAnchor, curFocus), prim.to()) == 0)
            { return }

            var start = posToDOM(this.cm, prim.from())
            var end = posToDOM(this.cm, prim.to())
            if (!start && !end) { return }

            var view = this.cm.display.view
            var old = sel.rangeCount && sel.getRangeAt(0)
            if (!start) {
                start = {node: view[0].measure.map[2], offset: 0}
            } else if (!end) { // FIXME dangerously hacky
                var measure = view[view.length - 1].measure
                var map$$1 = measure.maps ? measure.maps[measure.maps.length - 1] : measure.map
                end = {node: map$$1[map$$1.length - 1], offset: map$$1[map$$1.length - 2] - map$$1[map$$1.length - 3]}
            }

            var rng
            try { rng = range(start.node, start.offset, end.offset, end.node) }
            catch(e) {} // Our model of the DOM might be outdated, in which case the range we try to set can be impossible
            if (rng) {
                if (!gecko && this.cm.state.focused) {
                    sel.collapse(start.node, start.offset)
                    if (!rng.collapsed) { sel.addRange(rng) }
                } else {
                    sel.removeAllRanges()
                    sel.addRange(rng)
                }
                if (old && sel.anchorNode == null) { sel.addRange(old) }
                else if (gecko) { this.startGracePeriod() }
            }
            this.rememberSelection()
        },

        startGracePeriod: function() {
            var this$1 = this;

            clearTimeout(this.gracePeriod)
            this.gracePeriod = setTimeout(function () {
                this$1.gracePeriod = false
                if (this$1.selectionChanged())
                { this$1.cm.operation(function () { return this$1.cm.curOp.selectionChanged = true; }) }
            }, 20)
        },

        showMultipleSelections: function(info) {
            removeChildrenAndAdd(this.cm.display.cursorDiv, info.cursors)
            removeChildrenAndAdd(this.cm.display.selectionDiv, info.selection)
        },

        rememberSelection: function() {
            var sel = window.getSelection()
            this.lastAnchorNode = sel.anchorNode; this.lastAnchorOffset = sel.anchorOffset
            this.lastFocusNode = sel.focusNode; this.lastFocusOffset = sel.focusOffset
        },

        selectionInEditor: function() {
            var sel = window.getSelection()
            if (!sel.rangeCount) { return false }
            var node = sel.getRangeAt(0).commonAncestorContainer
            return contains(this.div, node)
        },

        focus: function() {
            if (this.cm.options.readOnly != "nocursor") { this.div.focus() }
        },
        blur: function() { this.div.blur() },
        getField: function() { return this.div },

        supportsTouch: function() { return true },

        receivedFocus: function() {
            var input = this
            if (this.selectionInEditor())
            { this.pollSelection() }
            else
            { runInOp(this.cm, function () { return input.cm.curOp.selectionChanged = true; }) }

            function poll() {
                if (input.cm.state.focused) {
                    input.pollSelection()
                    input.polling.set(input.cm.options.pollInterval, poll)
                }
            }
            this.polling.set(this.cm.options.pollInterval, poll)
        },

        selectionChanged: function() {
            var sel = window.getSelection()
            return sel.anchorNode != this.lastAnchorNode || sel.anchorOffset != this.lastAnchorOffset ||
                sel.focusNode != this.lastFocusNode || sel.focusOffset != this.lastFocusOffset
        },

        pollSelection: function() {
            if (!this.composing && !this.gracePeriod && this.selectionChanged()) {
                var sel = window.getSelection(), cm = this.cm
                this.rememberSelection()
                var anchor = domToPos(cm, sel.anchorNode, sel.anchorOffset)
                var head = domToPos(cm, sel.focusNode, sel.focusOffset)
                if (anchor && head) { runInOp(cm, function () {
                    setSelection(cm.doc, simpleSelection(anchor, head), sel_dontScroll)
                    if (anchor.bad || head.bad) { cm.curOp.selectionChanged = true }
                }) }
            }
        },

        pollContent: function() {
            var cm = this.cm, display = cm.display, sel = cm.doc.sel.primary()
            var from = sel.from(), to = sel.to()
            if (from.line < display.viewFrom || to.line > display.viewTo - 1) { return false }

            var fromIndex, fromLine, fromNode
            if (from.line == display.viewFrom || (fromIndex = findViewIndex(cm, from.line)) == 0) {
                fromLine = lineNo(display.view[0].line)
                fromNode = display.view[0].node
            } else {
                fromLine = lineNo(display.view[fromIndex].line)
                fromNode = display.view[fromIndex - 1].node.nextSibling
            }
            var toIndex = findViewIndex(cm, to.line)
            var toLine, toNode
            if (toIndex == display.view.length - 1) {
                toLine = display.viewTo - 1
                toNode = display.lineDiv.lastChild
            } else {
                toLine = lineNo(display.view[toIndex + 1].line) - 1
                toNode = display.view[toIndex + 1].node.previousSibling
            }

            var newText = cm.doc.splitLines(domTextBetween(cm, fromNode, toNode, fromLine, toLine))
            var oldText = getBetween(cm.doc, Pos(fromLine, 0), Pos(toLine, getLine(cm.doc, toLine).text.length))
            while (newText.length > 1 && oldText.length > 1) {
                if (lst(newText) == lst(oldText)) { newText.pop(); oldText.pop(); toLine-- }
                else if (newText[0] == oldText[0]) { newText.shift(); oldText.shift(); fromLine++ }
                else { break }
            }

            var cutFront = 0, cutEnd = 0
            var newTop = newText[0], oldTop = oldText[0], maxCutFront = Math.min(newTop.length, oldTop.length)
            while (cutFront < maxCutFront && newTop.charCodeAt(cutFront) == oldTop.charCodeAt(cutFront))
            { ++cutFront }
            var newBot = lst(newText), oldBot = lst(oldText)
            var maxCutEnd = Math.min(newBot.length - (newText.length == 1 ? cutFront : 0),
                oldBot.length - (oldText.length == 1 ? cutFront : 0))
            while (cutEnd < maxCutEnd &&
            newBot.charCodeAt(newBot.length - cutEnd - 1) == oldBot.charCodeAt(oldBot.length - cutEnd - 1))
            { ++cutEnd }

            newText[newText.length - 1] = newBot.slice(0, newBot.length - cutEnd)
            newText[0] = newText[0].slice(cutFront)

            var chFrom = Pos(fromLine, cutFront)
            var chTo = Pos(toLine, oldText.length ? lst(oldText).length - cutEnd : 0)
            if (newText.length > 1 || newText[0] || cmp(chFrom, chTo)) {
                replaceRange(cm.doc, newText, chFrom, chTo, "+input")
                return true
            }
        },

        ensurePolled: function() {
            this.forceCompositionEnd()
        },
        reset: function() {
            this.forceCompositionEnd()
        },
        forceCompositionEnd: function() {
            if (!this.composing || this.composing.handled) { return }
            this.applyComposition(this.composing)
            this.composing.handled = true
            this.div.blur()
            this.div.focus()
        },
        applyComposition: function(composing) {
            if (this.cm.isReadOnly())
            { operation(this.cm, regChange)(this.cm) }
            else if (composing.data && composing.data != composing.startData)
            { operation(this.cm, applyTextInput)(this.cm, composing.data, 0, composing.sel) }
        },

        setUneditable: function(node) {
            node.contentEditable = "false"
        },

        onKeyPress: function(e) {
            e.preventDefault()
            if (!this.cm.isReadOnly())
            { operation(this.cm, applyTextInput)(this.cm, String.fromCharCode(e.charCode == null ? e.keyCode : e.charCode), 0) }
        },

        readOnlyChanged: function(val) {
            this.div.contentEditable = String(val != "nocursor")
        },

        onContextMenu: nothing,
        resetPosition: nothing,

        needsContentAttribute: true
    }, ContentEditableInput.prototype)

    function posToDOM(cm, pos) {
        var view = findViewForLine(cm, pos.line)
        if (!view || view.hidden) { return null }
        var line = getLine(cm.doc, pos.line)
        var info = mapFromLineView(view, line, pos.line)

        var order = getOrder(line), side = "left"
        if (order) {
            var partPos = getBidiPartAt(order, pos.ch)
            side = partPos % 2 ? "right" : "left"
        }
        var result = nodeAndOffsetInLineMap(info.map, pos.ch, side)
        result.offset = result.collapse == "right" ? result.end : result.start
        return result
    }

    function badPos(pos, bad) { if (bad) { pos.bad = true; } return pos }

    function domTextBetween(cm, from, to, fromLine, toLine) {
        var text = "", closing = false, lineSep = cm.doc.lineSeparator()
        function recognizeMarker(id) { return function (marker) { return marker.id == id; } }
        function walk(node) {
            if (node.nodeType == 1) {
                var cmText = node.getAttribute("cm-text")
                if (cmText != null) {
                    if (cmText == "") { cmText = node.textContent.replace(/\u200b/g, "") }
                    text += cmText
                    return
                }
                var markerID = node.getAttribute("cm-marker"), range$$1
                if (markerID) {
                    var found = cm.findMarks(Pos(fromLine, 0), Pos(toLine + 1, 0), recognizeMarker(+markerID))
                    if (found.length && (range$$1 = found[0].find()))
                    { text += getBetween(cm.doc, range$$1.from, range$$1.to).join(lineSep) }
                    return
                }
                if (node.getAttribute("contenteditable") == "false") { return }
                for (var i = 0; i < node.childNodes.length; i++)
                { walk(node.childNodes[i]) }
                if (/^(pre|div|p)$/i.test(node.nodeName))
                { closing = true }
            } else if (node.nodeType == 3) {
                var val = node.nodeValue
                if (!val) { return }
                if (closing) {
                    text += lineSep
                    closing = false
                }
                text += val
            }
        }
        for (;;) {
            walk(from)
            if (from == to) { break }
            from = from.nextSibling
        }
        return text
    }

    function domToPos(cm, node, offset) {
        var lineNode
        if (node == cm.display.lineDiv) {
            lineNode = cm.display.lineDiv.childNodes[offset]
            if (!lineNode) { return badPos(cm.clipPos(Pos(cm.display.viewTo - 1)), true) }
            node = null; offset = 0
        } else {
            for (lineNode = node;; lineNode = lineNode.parentNode) {
                if (!lineNode || lineNode == cm.display.lineDiv) { return null }
                if (lineNode.parentNode && lineNode.parentNode == cm.display.lineDiv) { break }
            }
        }
        for (var i = 0; i < cm.display.view.length; i++) {
            var lineView = cm.display.view[i]
            if (lineView.node == lineNode)
            { return locateNodeInLineView(lineView, node, offset) }
        }
    }

    function locateNodeInLineView(lineView, node, offset) {
        var wrapper = lineView.text.firstChild, bad = false
        if (!node || !contains(wrapper, node)) { return badPos(Pos(lineNo(lineView.line), 0), true) }
        if (node == wrapper) {
            bad = true
            node = wrapper.childNodes[offset]
            offset = 0
            if (!node) {
                var line = lineView.rest ? lst(lineView.rest) : lineView.line
                return badPos(Pos(lineNo(line), line.text.length), bad)
            }
        }

        var textNode = node.nodeType == 3 ? node : null, topNode = node
        if (!textNode && node.childNodes.length == 1 && node.firstChild.nodeType == 3) {
            textNode = node.firstChild
            if (offset) { offset = textNode.nodeValue.length }
        }
        while (topNode.parentNode != wrapper) { topNode = topNode.parentNode }
        var measure = lineView.measure, maps = measure.maps

        function find(textNode, topNode, offset) {
            for (var i = -1; i < (maps ? maps.length : 0); i++) {
                var map$$1 = i < 0 ? measure.map : maps[i]
                for (var j = 0; j < map$$1.length; j += 3) {
                    var curNode = map$$1[j + 2]
                    if (curNode == textNode || curNode == topNode) {
                        var line = lineNo(i < 0 ? lineView.line : lineView.rest[i])
                        var ch = map$$1[j] + offset
                        if (offset < 0 || curNode != textNode) { ch = map$$1[j + (offset ? 1 : 0)] }
                        return Pos(line, ch)
                    }
                }
            }
        }
        var found = find(textNode, topNode, offset)
        if (found) { return badPos(found, bad) }

        // FIXME this is all really shaky. might handle the few cases it needs to handle, but likely to cause problems
        for (var after = topNode.nextSibling, dist = textNode ? textNode.nodeValue.length - offset : 0; after; after = after.nextSibling) {
            found = find(after, after.firstChild, 0)
            if (found)
            { return badPos(Pos(found.line, found.ch - dist), bad) }
            else
            { dist += after.textContent.length }
        }
        for (var before = topNode.previousSibling, dist$1 = offset; before; before = before.previousSibling) {
            found = find(before, before.firstChild, -1)
            if (found)
            { return badPos(Pos(found.line, found.ch + dist$1), bad) }
            else
            { dist$1 += before.textContent.length }
        }
    }

// TEXTAREA INPUT STYLE

    function TextareaInput(cm) {
        this.cm = cm
        // See input.poll and input.reset
        this.prevInput = ""

        // Flag that indicates whether we expect input to appear real soon
        // now (after some event like 'keypress' or 'input') and are
        // polling intensively.
        this.pollingFast = false
        // Self-resetting timeout for the poller
        this.polling = new Delayed()
        // Tracks when input.reset has punted to just putting a short
        // string into the textarea instead of the full selection.
        this.inaccurateSelection = false
        // Used to work around IE issue with selection being forgotten when focus moves away from textarea
        this.hasSelection = false
        this.composing = null
    }

    TextareaInput.prototype = copyObj({
        init: function(display) {
            var this$1 = this;

            var input = this, cm = this.cm

            // Wraps and hides input textarea
            var div = this.wrapper = hiddenTextarea()
            // The semihidden textarea that is focused when the editor is
            // focused, and receives input.
            var te = this.textarea = div.firstChild
            display.wrapper.insertBefore(div, display.wrapper.firstChild)

            // Needed to hide big blue blinking cursor on Mobile Safari (doesn't seem to work in iOS 8 anymore)
            if (ios) { te.style.width = "0px" }

            on(te, "input", function () {
                if (ie && ie_version >= 9 && this$1.hasSelection) { this$1.hasSelection = null }
                input.poll()
            })

            on(te, "paste", function (e) {
                if (signalDOMEvent(cm, e) || handlePaste(e, cm)) { return }

                cm.state.pasteIncoming = true
                input.fastPoll()
            })

            function prepareCopyCut(e) {
                if (signalDOMEvent(cm, e)) { return }
                if (cm.somethingSelected()) {
                    setLastCopied({lineWise: false, text: cm.getSelections()})
                    if (input.inaccurateSelection) {
                        input.prevInput = ""
                        input.inaccurateSelection = false
                        te.value = lastCopied.text.join("\n")
                        selectInput(te)
                    }
                } else if (!cm.options.lineWiseCopyCut) {
                    return
                } else {
                    var ranges = copyableRanges(cm)
                    setLastCopied({lineWise: true, text: ranges.text})
                    if (e.type == "cut") {
                        cm.setSelections(ranges.ranges, null, sel_dontScroll)
                    } else {
                        input.prevInput = ""
                        te.value = ranges.text.join("\n")
                        selectInput(te)
                    }
                }
                if (e.type == "cut") { cm.state.cutIncoming = true }
            }
            on(te, "cut", prepareCopyCut)
            on(te, "copy", prepareCopyCut)

            on(display.scroller, "paste", function (e) {
                if (eventInWidget(display, e) || signalDOMEvent(cm, e)) { return }
                cm.state.pasteIncoming = true
                input.focus()
            })

            // Prevent normal selection in the editor (we handle our own)
            on(display.lineSpace, "selectstart", function (e) {
                if (!eventInWidget(display, e)) { e_preventDefault(e) }
            })

            on(te, "compositionstart", function () {
                var start = cm.getCursor("from")
                if (input.composing) { input.composing.range.clear() }
                input.composing = {
                    start: start,
                    range: cm.markText(start, cm.getCursor("to"), {className: "CodeMirror-composing"})
                }
            })
            on(te, "compositionend", function () {
                if (input.composing) {
                    input.poll()
                    input.composing.range.clear()
                    input.composing = null
                }
            })
        },

        prepareSelection: function() {
            // Redraw the selection and/or cursor
            var cm = this.cm, display = cm.display, doc = cm.doc
            var result = prepareSelection(cm)

            // Move the hidden textarea near the cursor to prevent scrolling artifacts
            if (cm.options.moveInputWithCursor) {
                var headPos = cursorCoords(cm, doc.sel.primary().head, "div")
                var wrapOff = display.wrapper.getBoundingClientRect(), lineOff = display.lineDiv.getBoundingClientRect()
                result.teTop = Math.max(0, Math.min(display.wrapper.clientHeight - 10,
                    headPos.top + lineOff.top - wrapOff.top))
                result.teLeft = Math.max(0, Math.min(display.wrapper.clientWidth - 10,
                    headPos.left + lineOff.left - wrapOff.left))
            }

            return result
        },

        showSelection: function(drawn) {
            var cm = this.cm, display = cm.display
            removeChildrenAndAdd(display.cursorDiv, drawn.cursors)
            removeChildrenAndAdd(display.selectionDiv, drawn.selection)
            if (drawn.teTop != null) {
                this.wrapper.style.top = drawn.teTop + "px"
                this.wrapper.style.left = drawn.teLeft + "px"
            }
        },

        // Reset the input to correspond to the selection (or to be empty,
        // when not typing and nothing is selected)
        reset: function(typing) {
            if (this.contextMenuPending) { return }
            var minimal, selected, cm = this.cm, doc = cm.doc
            if (cm.somethingSelected()) {
                this.prevInput = ""
                var range$$1 = doc.sel.primary()
                minimal = hasCopyEvent &&
                    (range$$1.to().line - range$$1.from().line > 100 || (selected = cm.getSelection()).length > 1000)
                var content = minimal ? "-" : selected || cm.getSelection()
                this.textarea.value = content
                if (cm.state.focused) { selectInput(this.textarea) }
                if (ie && ie_version >= 9) { this.hasSelection = content }
            } else if (!typing) {
                this.prevInput = this.textarea.value = ""
                if (ie && ie_version >= 9) { this.hasSelection = null }
            }
            this.inaccurateSelection = minimal
        },

        getField: function() { return this.textarea },

        supportsTouch: function() { return false },

        focus: function() {
            if (this.cm.options.readOnly != "nocursor" && (!mobile || activeElt() != this.textarea)) {
                try { this.textarea.focus() }
                catch (e) {} // IE8 will throw if the textarea is display: none or not in DOM
            }
        },

        blur: function() { this.textarea.blur() },

        resetPosition: function() {
            this.wrapper.style.top = this.wrapper.style.left = 0
        },

        receivedFocus: function() { this.slowPoll() },

        // Poll for input changes, using the normal rate of polling. This
        // runs as long as the editor is focused.
        slowPoll: function() {
            var this$1 = this;

            if (this.pollingFast) { return }
            this.polling.set(this.cm.options.pollInterval, function () {
                this$1.poll()
                if (this$1.cm.state.focused) { this$1.slowPoll() }
            })
        },

        // When an event has just come in that is likely to add or change
        // something in the input textarea, we poll faster, to ensure that
        // the change appears on the screen quickly.
        fastPoll: function() {
            var missed = false, input = this
            input.pollingFast = true
            function p() {
                var changed = input.poll()
                if (!changed && !missed) {missed = true; input.polling.set(60, p)}
                else {input.pollingFast = false; input.slowPoll()}
            }
            input.polling.set(20, p)
        },

        // Read input from the textarea, and update the document to match.
        // When something is selected, it is present in the textarea, and
        // selected (unless it is huge, in which case a placeholder is
        // used). When nothing is selected, the cursor sits after previously
        // seen text (can be empty), which is stored in prevInput (we must
        // not reset the textarea when typing, because that breaks IME).
        poll: function() {
            var this$1 = this;

            var cm = this.cm, input = this.textarea, prevInput = this.prevInput
            // Since this is called a *lot*, try to bail out as cheaply as
            // possible when it is clear that nothing happened. hasSelection
            // will be the case when there is a lot of text in the textarea,
            // in which case reading its value would be expensive.
            if (this.contextMenuPending || !cm.state.focused ||
                (hasSelection(input) && !prevInput && !this.composing) ||
                cm.isReadOnly() || cm.options.disableInput || cm.state.keySeq)
            { return false }

            var text = input.value
            // If nothing changed, bail.
            if (text == prevInput && !cm.somethingSelected()) { return false }
            // Work around nonsensical selection resetting in IE9/10, and
            // inexplicable appearance of private area unicode characters on
            // some key combos in Mac (#2689).
            if (ie && ie_version >= 9 && this.hasSelection === text ||
                mac && /[\uf700-\uf7ff]/.test(text)) {
                cm.display.input.reset()
                return false
            }

            if (cm.doc.sel == cm.display.selForContextMenu) {
                var first = text.charCodeAt(0)
                if (first == 0x200b && !prevInput) { prevInput = "\u200b" }
                if (first == 0x21da) { this.reset(); return this.cm.execCommand("undo") }
            }
            // Find the part of the input that is actually new
            var same = 0, l = Math.min(prevInput.length, text.length)
            while (same < l && prevInput.charCodeAt(same) == text.charCodeAt(same)) { ++same }

            runInOp(cm, function () {
                applyTextInput(cm, text.slice(same), prevInput.length - same,
                    null, this$1.composing ? "*compose" : null)

                // Don't leave long text in the textarea, since it makes further polling slow
                if (text.length > 1000 || text.indexOf("\n") > -1) { input.value = this$1.prevInput = "" }
                else { this$1.prevInput = text }

                if (this$1.composing) {
                    this$1.composing.range.clear()
                    this$1.composing.range = cm.markText(this$1.composing.start, cm.getCursor("to"),
                        {className: "CodeMirror-composing"})
                }
            })
            return true
        },

        ensurePolled: function() {
            if (this.pollingFast && this.poll()) { this.pollingFast = false }
        },

        onKeyPress: function() {
            if (ie && ie_version >= 9) { this.hasSelection = null }
            this.fastPoll()
        },

        onContextMenu: function(e) {
            var input = this, cm = input.cm, display = cm.display, te = input.textarea
            var pos = posFromMouse(cm, e), scrollPos = display.scroller.scrollTop
            if (!pos || presto) { return } // Opera is difficult.

            // Reset the current text selection only if the click is done outside of the selection
            // and 'resetSelectionOnContextMenu' option is true.
            var reset = cm.options.resetSelectionOnContextMenu
            if (reset && cm.doc.sel.contains(pos) == -1)
            { operation(cm, setSelection)(cm.doc, simpleSelection(pos), sel_dontScroll) }

            var oldCSS = te.style.cssText, oldWrapperCSS = input.wrapper.style.cssText
            input.wrapper.style.cssText = "position: absolute"
            var wrapperBox = input.wrapper.getBoundingClientRect()
            te.style.cssText = "position: absolute; width: 30px; height: 30px;\n      top: " + (e.clientY - wrapperBox.top - 5) + "px; left: " + (e.clientX - wrapperBox.left - 5) + "px;\n      z-index: 1000; background: " + (ie ? "rgba(255, 255, 255, .05)" : "transparent") + ";\n      outline: none; border-width: 0; outline: none; overflow: hidden; opacity: .05; filter: alpha(opacity=5);"
            var oldScrollY
            if (webkit) { oldScrollY = window.scrollY } // Work around Chrome issue (#2712)
            display.input.focus()
            if (webkit) { window.scrollTo(null, oldScrollY) }
            display.input.reset()
            // Adds "Select all" to context menu in FF
            if (!cm.somethingSelected()) { te.value = input.prevInput = " " }
            input.contextMenuPending = true
            display.selForContextMenu = cm.doc.sel
            clearTimeout(display.detectingSelectAll)

            // Select-all will be greyed out if there's nothing to select, so
            // this adds a zero-width space so that we can later check whether
            // it got selected.
            function prepareSelectAllHack() {
                if (te.selectionStart != null) {
                    var selected = cm.somethingSelected()
                    var extval = "\u200b" + (selected ? te.value : "")
                    te.value = "\u21da" // Used to catch context-menu undo
                    te.value = extval
                    input.prevInput = selected ? "" : "\u200b"
                    te.selectionStart = 1; te.selectionEnd = extval.length
                    // Re-set this, in case some other handler touched the
                    // selection in the meantime.
                    display.selForContextMenu = cm.doc.sel
                }
            }
            function rehide() {
                input.contextMenuPending = false
                input.wrapper.style.cssText = oldWrapperCSS
                te.style.cssText = oldCSS
                if (ie && ie_version < 9) { display.scrollbars.setScrollTop(display.scroller.scrollTop = scrollPos) }

                // Try to detect the user choosing select-all
                if (te.selectionStart != null) {
                    if (!ie || (ie && ie_version < 9)) { prepareSelectAllHack() }
                    var i = 0, poll = function () {
                        if (display.selForContextMenu == cm.doc.sel && te.selectionStart == 0 &&
                            te.selectionEnd > 0 && input.prevInput == "\u200b")
                        { operation(cm, selectAll)(cm) }
                        else if (i++ < 10) { display.detectingSelectAll = setTimeout(poll, 500) }
                        else { display.input.reset() }
                    }
                    display.detectingSelectAll = setTimeout(poll, 200)
                }
            }

            if (ie && ie_version >= 9) { prepareSelectAllHack() }
            if (captureRightClick) {
                e_stop(e)
                var mouseup = function () {
                    off(window, "mouseup", mouseup)
                    setTimeout(rehide, 20)
                }
                on(window, "mouseup", mouseup)
            } else {
                setTimeout(rehide, 50)
            }
        },

        readOnlyChanged: function(val) {
            if (!val) { this.reset() }
        },

        setUneditable: nothing,

        needsContentAttribute: false
    }, TextareaInput.prototype)

    function fromTextArea(textarea, options) {
        options = options ? copyObj(options) : {}
        options.value = textarea.value
        if (!options.tabindex && textarea.tabIndex)
        { options.tabindex = textarea.tabIndex }
        if (!options.placeholder && textarea.placeholder)
        { options.placeholder = textarea.placeholder }
        // Set autofocus to true if this textarea is focused, or if it has
        // autofocus and no other element is focused.
        if (options.autofocus == null) {
            var hasFocus = activeElt()
            options.autofocus = hasFocus == textarea ||
                textarea.getAttribute("autofocus") != null && hasFocus == document.body
        }

        function save() {textarea.value = cm.getValue()}

        var realSubmit
        if (textarea.form) {
            on(textarea.form, "submit", save)
            // Deplorable hack to make the submit method do the right thing.
            if (!options.leaveSubmitMethodAlone) {
                var form = textarea.form
                realSubmit = form.submit
                try {
                    var wrappedSubmit = form.submit = function () {
                        save()
                        form.submit = realSubmit
                        form.submit()
                        form.submit = wrappedSubmit
                    }
                } catch(e) {}
            }
        }

        options.finishInit = function (cm) {
            cm.save = save
            cm.getTextArea = function () { return textarea; }
            cm.toTextArea = function () {
                cm.toTextArea = isNaN // Prevent this from being ran twice
                save()
                textarea.parentNode.removeChild(cm.getWrapperElement())
                textarea.style.display = ""
                if (textarea.form) {
                    off(textarea.form, "submit", save)
                    if (typeof textarea.form.submit == "function")
                    { textarea.form.submit = realSubmit }
                }
            }
        }

        textarea.style.display = "none"
        var cm = CodeMirror$1(function (node) { return textarea.parentNode.insertBefore(node, textarea.nextSibling); },
            options)
        return cm
    }

    function addLegacyProps(CodeMirror) {
        CodeMirror.off = off
        CodeMirror.on = on
        CodeMirror.wheelEventPixels = wheelEventPixels
        CodeMirror.Doc = Doc
        CodeMirror.splitLines = splitLinesAuto
        CodeMirror.countColumn = countColumn
        CodeMirror.findColumn = findColumn
        CodeMirror.isWordChar = isWordCharBasic
        CodeMirror.Pass = Pass
        CodeMirror.signal = signal
        CodeMirror.Line = Line
        CodeMirror.changeEnd = changeEnd
        CodeMirror.scrollbarModel = scrollbarModel
        CodeMirror.Pos = Pos
        CodeMirror.cmpPos = cmp
        CodeMirror.modes = modes
        CodeMirror.mimeModes = mimeModes
        CodeMirror.resolveMode = resolveMode
        CodeMirror.getMode = getMode
        CodeMirror.modeExtensions = modeExtensions
        CodeMirror.extendMode = extendMode
        CodeMirror.copyState = copyState
        CodeMirror.startState = startState
        CodeMirror.innerMode = innerMode
        CodeMirror.commands = commands
        CodeMirror.keyMap = keyMap
        CodeMirror.keyName = keyName
        CodeMirror.isModifierKey = isModifierKey
        CodeMirror.lookupKey = lookupKey
        CodeMirror.normalizeKeyMap = normalizeKeyMap
        CodeMirror.StringStream = StringStream
        CodeMirror.SharedTextMarker = SharedTextMarker
        CodeMirror.TextMarker = TextMarker
        CodeMirror.LineWidget = LineWidget
        CodeMirror.e_preventDefault = e_preventDefault
        CodeMirror.e_stopPropagation = e_stopPropagation
        CodeMirror.e_stop = e_stop
        CodeMirror.addClass = addClass
        CodeMirror.contains = contains
        CodeMirror.rmClass = rmClass
        CodeMirror.keyNames = keyNames
    }

// EDITOR CONSTRUCTOR

    defineOptions(CodeMirror$1)

    addEditorMethods(CodeMirror$1)

// Set up methods on CodeMirror's prototype to redirect to the editor's document.
    var dontDelegate = "iter insert remove copy getEditor constructor".split(" ")
    for (var prop in Doc.prototype) { if (Doc.prototype.hasOwnProperty(prop) && indexOf(dontDelegate, prop) < 0)
    { CodeMirror$1.prototype[prop] = (function(method) {
        return function() {return method.apply(this.doc, arguments)}
    })(Doc.prototype[prop]) } }

    eventMixin(Doc)

// INPUT HANDLING

    CodeMirror$1.inputStyles = {"textarea": TextareaInput, "contenteditable": ContentEditableInput}

// MODE DEFINITION AND QUERYING

// Extra arguments are stored as the mode's dependencies, which is
// used by (legacy) mechanisms like loadmode.js to automatically
// load a mode. (Preferred mechanism is the require/define calls.)
    CodeMirror$1.defineMode = function(name/*, mode, …*/) {
        if (!CodeMirror$1.defaults.mode && name != "null") { CodeMirror$1.defaults.mode = name }
        defineMode.apply(this, arguments)
    }

    CodeMirror$1.defineMIME = defineMIME

// Minimal default mode.
    CodeMirror$1.defineMode("null", function () { return ({token: function (stream) { return stream.skipToEnd(); }}); })
    CodeMirror$1.defineMIME("text/plain", "null")

// EXTENSIONS

    CodeMirror$1.defineExtension = function (name, func) {
        CodeMirror$1.prototype[name] = func
    }
    CodeMirror$1.defineDocExtension = function (name, func) {
        Doc.prototype[name] = func
    }

    CodeMirror$1.fromTextArea = fromTextArea

    addLegacyProps(CodeMirror$1)

    CodeMirror$1.version = "5.19.1"

    return CodeMirror$1;

})));
    <?php
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}


function resource_88b57a5c8ca3926b82c144324e9c01a2() {
    ob_start(); ?>
    // CodeMirror, copyright (c) by Marijn Haverbeke and others
// Distributed under an MIT license: http://codemirror.net/LICENSE

(function(mod) {
    if (typeof exports == "object" && typeof module == "object") // CommonJS
        mod(require("../../lib/codemirror"), require("../xml/xml"), require("../meta"));
    else if (typeof define == "function" && define.amd) // AMD
        define(["../../lib/codemirror", "../xml/xml", "../meta"], mod);
    else // Plain browser env
        mod(CodeMirror);
})(function(CodeMirror) {
    "use strict";

    CodeMirror.defineMode("markdown", function(cmCfg, modeCfg) {

        var htmlMode = CodeMirror.getMode(cmCfg, "text/html");
        var htmlModeMissing = htmlMode.name == "null"

        function getMode(name) {
            if (CodeMirror.findModeByName) {
                var found = CodeMirror.findModeByName(name);
                if (found) name = found.mime || found.mimes[0];
            }
            var mode = CodeMirror.getMode(cmCfg, name);
            return mode.name == "null" ? null : mode;
        }

        // Should characters that affect highlighting be highlighted separate?
        // Does not include characters that will be output (such as `1.` and `-` for lists)
        if (modeCfg.highlightFormatting === undefined)
            modeCfg.highlightFormatting = false;

        // Maximum number of nested blockquotes. Set to 0 for infinite nesting.
        // Excess `>` will emit `error` token.
        if (modeCfg.maxBlockquoteDepth === undefined)
            modeCfg.maxBlockquoteDepth = 0;

        // Should underscores in words open/close em/strong?
        if (modeCfg.underscoresBreakWords === undefined)
            modeCfg.underscoresBreakWords = true;

        // Use `fencedCodeBlocks` to configure fenced code blocks. false to
        // disable, string to specify a precise regexp that the fence should
        // match, and true to allow three or more backticks or tildes (as
        // per CommonMark).

        // Turn on task lists? ("- [ ] " and "- [x] ")
        if (modeCfg.taskLists === undefined) modeCfg.taskLists = false;

        // Turn on strikethrough syntax
        if (modeCfg.strikethrough === undefined)
            modeCfg.strikethrough = false;

        // Allow token types to be overridden by user-provided token types.
        if (modeCfg.tokenTypeOverrides === undefined)
            modeCfg.tokenTypeOverrides = {};

        var tokenTypes = {
            header: "header",
            code: "comment",
            quote: "quote",
            list1: "variable-2",
            list2: "variable-3",
            list3: "keyword",
            hr: "hr",
            image: "image",
            imageAltText: "image-alt-text",
            imageMarker: "image-marker",
            formatting: "formatting",
            linkInline: "link",
            linkEmail: "link",
            linkText: "link",
            linkHref: "string",
            em: "em",
            strong: "strong",
            strikethrough: "strikethrough"
        };

        for (var tokenType in tokenTypes) {
            if (tokenTypes.hasOwnProperty(tokenType) && modeCfg.tokenTypeOverrides[tokenType]) {
                tokenTypes[tokenType] = modeCfg.tokenTypeOverrides[tokenType];
            }
        }

        var hrRE = /^([*\-_])(?:\s*\1){2,}\s*$/
            ,   ulRE = /^[*\-+]\s+/
            ,   olRE = /^[0-9]+([.)])\s+/
            ,   taskListRE = /^\[(x| )\](?=\s)/ // Must follow ulRE or olRE
            ,   atxHeaderRE = modeCfg.allowAtxHeaderWithoutSpace ? /^(#+)/ : /^(#+)(?: |$)/
            ,   setextHeaderRE = /^ *(?:\={1,}|-{1,})\s*$/
            ,   textRE = /^[^#!\[\]*_\\<>` "'(~]+/
            ,   fencedCodeRE = new RegExp("^(" + (modeCfg.fencedCodeBlocks === true ? "~~~+|```+" : modeCfg.fencedCodeBlocks) +
            ")[ \\t]*([\\w+#\-]*)");

        function switchInline(stream, state, f) {
            state.f = state.inline = f;
            return f(stream, state);
        }

        function switchBlock(stream, state, f) {
            state.f = state.block = f;
            return f(stream, state);
        }

        function lineIsEmpty(line) {
            return !line || !/\S/.test(line.string)
        }

        // Blocks

        function blankLine(state) {
            // Reset linkTitle state
            state.linkTitle = false;
            // Reset EM state
            state.em = false;
            // Reset STRONG state
            state.strong = false;
            // Reset strikethrough state
            state.strikethrough = false;
            // Reset state.quote
            state.quote = 0;
            // Reset state.indentedCode
            state.indentedCode = false;
            if (htmlModeMissing && state.f == htmlBlock) {
                state.f = inlineNormal;
                state.block = blockNormal;
            }
            // Reset state.trailingSpace
            state.trailingSpace = 0;
            state.trailingSpaceNewLine = false;
            // Mark this line as blank
            state.prevLine = state.thisLine
            state.thisLine = null
            return null;
        }

        function blockNormal(stream, state) {

            var sol = stream.sol();

            var prevLineIsList = state.list !== false,
                prevLineIsIndentedCode = state.indentedCode;

            state.indentedCode = false;

            if (prevLineIsList) {
                if (state.indentationDiff >= 0) { // Continued list
                    if (state.indentationDiff < 4) { // Only adjust indentation if *not* a code block
                        state.indentation -= state.indentationDiff;
                    }
                    state.list = null;
                } else if (state.indentation > 0) {
                    state.list = null;
                } else { // No longer a list
                    state.list = false;
                }
            }

            var match = null;
            if (state.indentationDiff >= 4) {
                stream.skipToEnd();
                if (prevLineIsIndentedCode || lineIsEmpty(state.prevLine)) {
                    state.indentation -= 4;
                    state.indentedCode = true;
                    return tokenTypes.code;
                } else {
                    return null;
                }
            } else if (stream.eatSpace()) {
                return null;
            } else if ((match = stream.match(atxHeaderRE)) && match[1].length <= 6) {
                state.header = match[1].length;
                if (modeCfg.highlightFormatting) state.formatting = "header";
                state.f = state.inline;
                return getType(state);
            } else if (!lineIsEmpty(state.prevLine) && !state.quote && !prevLineIsList &&
                !prevLineIsIndentedCode && (match = stream.match(setextHeaderRE))) {
                state.header = match[0].charAt(0) == '=' ? 1 : 2;
                if (modeCfg.highlightFormatting) state.formatting = "header";
                state.f = state.inline;
                return getType(state);
            } else if (stream.eat('>')) {
                state.quote = sol ? 1 : state.quote + 1;
                if (modeCfg.highlightFormatting) state.formatting = "quote";
                stream.eatSpace();
                return getType(state);
            } else if (stream.peek() === '[') {
                return switchInline(stream, state, footnoteLink);
            } else if (stream.match(hrRE, true)) {
                state.hr = true;
                return tokenTypes.hr;
            } else if ((lineIsEmpty(state.prevLine) || prevLineIsList) && (stream.match(ulRE, false) || stream.match(olRE, false))) {
                var listType = null;
                if (stream.match(ulRE, true)) {
                    listType = 'ul';
                } else {
                    stream.match(olRE, true);
                    listType = 'ol';
                }
                state.indentation = stream.column() + stream.current().length;
                state.list = true;

                // While this list item's marker's indentation
                // is less than the deepest list item's content's indentation,
                // pop the deepest list item indentation off the stack.
                while (state.listStack && stream.column() < state.listStack[state.listStack.length - 1]) {
                    state.listStack.pop();
                }

                // Add this list item's content's indentation to the stack
                state.listStack.push(state.indentation);

                if (modeCfg.taskLists && stream.match(taskListRE, false)) {
                    state.taskList = true;
                }
                state.f = state.inline;
                if (modeCfg.highlightFormatting) state.formatting = ["list", "list-" + listType];
                return getType(state);
            } else if (modeCfg.fencedCodeBlocks && (match = stream.match(fencedCodeRE, true))) {
                state.fencedChars = match[1]
                // try switching mode
                state.localMode = getMode(match[2]);
                if (state.localMode) state.localState = CodeMirror.startState(state.localMode);
                state.f = state.block = local;
                if (modeCfg.highlightFormatting) state.formatting = "code-block";
                state.code = -1
                return getType(state);
            }

            return switchInline(stream, state, state.inline);
        }

        function htmlBlock(stream, state) {
            var style = htmlMode.token(stream, state.htmlState);
            if (!htmlModeMissing) {
                var inner = CodeMirror.innerMode(htmlMode, state.htmlState)
                if ((inner.mode.name == "xml" && inner.state.tagStart === null &&
                    (!inner.state.context && inner.state.tokenize.isInText)) ||
                    (state.md_inside && stream.current().indexOf(">") > -1)) {
                    state.f = inlineNormal;
                    state.block = blockNormal;
                    state.htmlState = null;
                }
            }
            return style;
        }

        function local(stream, state) {
            if (state.fencedChars && stream.match(state.fencedChars, false)) {
                state.localMode = state.localState = null;
                state.f = state.block = leavingLocal;
                return null;
            } else if (state.localMode) {
                return state.localMode.token(stream, state.localState);
            } else {
                stream.skipToEnd();
                return tokenTypes.code;
            }
        }

        function leavingLocal(stream, state) {
            stream.match(state.fencedChars);
            state.block = blockNormal;
            state.f = inlineNormal;
            state.fencedChars = null;
            if (modeCfg.highlightFormatting) state.formatting = "code-block";
            state.code = 1
            var returnType = getType(state);
            state.code = 0
            return returnType;
        }

        // Inline
        function getType(state) {
            var styles = [];

            if (state.formatting) {
                styles.push(tokenTypes.formatting);

                if (typeof state.formatting === "string") state.formatting = [state.formatting];

                for (var i = 0; i < state.formatting.length; i++) {
                    styles.push(tokenTypes.formatting + "-" + state.formatting[i]);

                    if (state.formatting[i] === "header") {
                        styles.push(tokenTypes.formatting + "-" + state.formatting[i] + "-" + state.header);
                    }

                    // Add `formatting-quote` and `formatting-quote-#` for blockquotes
                    // Add `error` instead if the maximum blockquote nesting depth is passed
                    if (state.formatting[i] === "quote") {
                        if (!modeCfg.maxBlockquoteDepth || modeCfg.maxBlockquoteDepth >= state.quote) {
                            styles.push(tokenTypes.formatting + "-" + state.formatting[i] + "-" + state.quote);
                        } else {
                            styles.push("error");
                        }
                    }
                }
            }

            if (state.taskOpen) {
                styles.push("meta");
                return styles.length ? styles.join(' ') : null;
            }
            if (state.taskClosed) {
                styles.push("property");
                return styles.length ? styles.join(' ') : null;
            }

            if (state.linkHref) {
                styles.push(tokenTypes.linkHref, "url");
            } else { // Only apply inline styles to non-url text
                if (state.strong) { styles.push(tokenTypes.strong); }
                if (state.em) { styles.push(tokenTypes.em); }
                if (state.strikethrough) { styles.push(tokenTypes.strikethrough); }
                if (state.linkText) { styles.push(tokenTypes.linkText); }
                if (state.code) { styles.push(tokenTypes.code); }
                if (state.image) { styles.push(tokenTypes.image); }
                if (state.imageAltText) { styles.push(tokenTypes.imageAltText, "link"); }
                if (state.imageMarker) { styles.push(tokenTypes.imageMarker); }
            }

            if (state.header) { styles.push(tokenTypes.header, tokenTypes.header + "-" + state.header); }

            if (state.quote) {
                styles.push(tokenTypes.quote);

                // Add `quote-#` where the maximum for `#` is modeCfg.maxBlockquoteDepth
                if (!modeCfg.maxBlockquoteDepth || modeCfg.maxBlockquoteDepth >= state.quote) {
                    styles.push(tokenTypes.quote + "-" + state.quote);
                } else {
                    styles.push(tokenTypes.quote + "-" + modeCfg.maxBlockquoteDepth);
                }
            }

            if (state.list !== false) {
                var listMod = (state.listStack.length - 1) % 3;
                if (!listMod) {
                    styles.push(tokenTypes.list1);
                } else if (listMod === 1) {
                    styles.push(tokenTypes.list2);
                } else {
                    styles.push(tokenTypes.list3);
                }
            }

            if (state.trailingSpaceNewLine) {
                styles.push("trailing-space-new-line");
            } else if (state.trailingSpace) {
                styles.push("trailing-space-" + (state.trailingSpace % 2 ? "a" : "b"));
            }

            return styles.length ? styles.join(' ') : null;
        }

        function handleText(stream, state) {
            if (stream.match(textRE, true)) {
                return getType(state);
            }
            return undefined;
        }

        function inlineNormal(stream, state) {
            var style = state.text(stream, state);
            if (typeof style !== 'undefined')
                return style;

            if (state.list) { // List marker (*, +, -, 1., etc)
                state.list = null;
                return getType(state);
            }

            if (state.taskList) {
                var taskOpen = stream.match(taskListRE, true)[1] !== "x";
                if (taskOpen) state.taskOpen = true;
                else state.taskClosed = true;
                if (modeCfg.highlightFormatting) state.formatting = "task";
                state.taskList = false;
                return getType(state);
            }

            state.taskOpen = false;
            state.taskClosed = false;

            if (state.header && stream.match(/^#+$/, true)) {
                if (modeCfg.highlightFormatting) state.formatting = "header";
                return getType(state);
            }

            // Get sol() value now, before character is consumed
            var sol = stream.sol();

            var ch = stream.next();

            // Matches link titles present on next line
            if (state.linkTitle) {
                state.linkTitle = false;
                var matchCh = ch;
                if (ch === '(') {
                    matchCh = ')';
                }
                matchCh = (matchCh+'').replace(/([.?*+^$[\]\\(){}|-])/g, "\\$1");
                var regex = '^\\s*(?:[^' + matchCh + '\\\\]+|\\\\\\\\|\\\\.)' + matchCh;
                if (stream.match(new RegExp(regex), true)) {
                    return tokenTypes.linkHref;
                }
            }

            // If this block is changed, it may need to be updated in GFM mode
            if (ch === '`') {
                var previousFormatting = state.formatting;
                if (modeCfg.highlightFormatting) state.formatting = "code";
                stream.eatWhile('`');
                var count = stream.current().length
                if (state.code == 0) {
                    state.code = count
                    return getType(state)
                } else if (count == state.code) { // Must be exact
                    var t = getType(state)
                    state.code = 0
                    return t
                } else {
                    state.formatting = previousFormatting
                    return getType(state)
                }
            } else if (state.code) {
                return getType(state);
            }

            if (ch === '\\') {
                stream.next();
                if (modeCfg.highlightFormatting) {
                    var type = getType(state);
                    var formattingEscape = tokenTypes.formatting + "-escape";
                    return type ? type + " " + formattingEscape : formattingEscape;
                }
            }

            if (ch === '!' && stream.match(/\[[^\]]*\] ?(?:\(|\[)/, false)) {
                state.imageMarker = true;
                state.image = true;
                if (modeCfg.highlightFormatting) state.formatting = "image";
                return getType(state);
            }

            if (ch === '[' && state.imageMarker) {
                state.imageMarker = false;
                state.imageAltText = true
                if (modeCfg.highlightFormatting) state.formatting = "image";
                return getType(state);
            }

            if (ch === ']' && state.imageAltText) {
                if (modeCfg.highlightFormatting) state.formatting = "image";
                var type = getType(state);
                state.imageAltText = false;
                state.image = false;
                state.inline = state.f = linkHref;
                return type;
            }

            if (ch === '[' && stream.match(/[^\]]*\](\(.*\)| ?\[.*?\])/, false) && !state.image) {
                state.linkText = true;
                if (modeCfg.highlightFormatting) state.formatting = "link";
                return getType(state);
            }

            if (ch === ']' && state.linkText && stream.match(/\(.*?\)| ?\[.*?\]/, false)) {
                if (modeCfg.highlightFormatting) state.formatting = "link";
                var type = getType(state);
                state.linkText = false;
                state.inline = state.f = linkHref;
                return type;
            }

            if (ch === '<' && stream.match(/^(https?|ftps?):\/\/(?:[^\\>]|\\.)+>/, false)) {
                state.f = state.inline = linkInline;
                if (modeCfg.highlightFormatting) state.formatting = "link";
                var type = getType(state);
                if (type){
                    type += " ";
                } else {
                    type = "";
                }
                return type + tokenTypes.linkInline;
            }

            if (ch === '<' && stream.match(/^[^> \\]+@(?:[^\\>]|\\.)+>/, false)) {
                state.f = state.inline = linkInline;
                if (modeCfg.highlightFormatting) state.formatting = "link";
                var type = getType(state);
                if (type){
                    type += " ";
                } else {
                    type = "";
                }
                return type + tokenTypes.linkEmail;
            }

            if (ch === '<' && stream.match(/^(!--|\w)/, false)) {
                var end = stream.string.indexOf(">", stream.pos);
                if (end != -1) {
                    var atts = stream.string.substring(stream.start, end);
                    if (/markdown\s*=\s*('|"){0,1}1('|"){0,1}/.test(atts)) state.md_inside = true;
                }
                stream.backUp(1);
                state.htmlState = CodeMirror.startState(htmlMode);
                return switchBlock(stream, state, htmlBlock);
            }

            if (ch === '<' && stream.match(/^\/\w*?>/)) {
                state.md_inside = false;
                return "tag";
            }

            var ignoreUnderscore = false;
            if (!modeCfg.underscoresBreakWords) {
                if (ch === '_' && stream.peek() !== '_' && stream.match(/(\w)/, false)) {
                    var prevPos = stream.pos - 2;
                    if (prevPos >= 0) {
                        var prevCh = stream.string.charAt(prevPos);
                        if (prevCh !== '_' && prevCh.match(/(\w)/, false)) {
                            ignoreUnderscore = true;
                        }
                    }
                }
            }
            if (ch === '*' || (ch === '_' && !ignoreUnderscore)) {
                if (sol && stream.peek() === ' ') {
                    // Do nothing, surrounded by newline and space
                } else if (state.strong === ch && stream.eat(ch)) { // Remove STRONG
                    if (modeCfg.highlightFormatting) state.formatting = "strong";
                    var t = getType(state);
                    state.strong = false;
                    return t;
                } else if (!state.strong && stream.eat(ch)) { // Add STRONG
                    state.strong = ch;
                    if (modeCfg.highlightFormatting) state.formatting = "strong";
                    return getType(state);
                } else if (state.em === ch) { // Remove EM
                    if (modeCfg.highlightFormatting) state.formatting = "em";
                    var t = getType(state);
                    state.em = false;
                    return t;
                } else if (!state.em) { // Add EM
                    state.em = ch;
                    if (modeCfg.highlightFormatting) state.formatting = "em";
                    return getType(state);
                }
            } else if (ch === ' ') {
                if (stream.eat('*') || stream.eat('_')) { // Probably surrounded by spaces
                    if (stream.peek() === ' ') { // Surrounded by spaces, ignore
                        return getType(state);
                    } else { // Not surrounded by spaces, back up pointer
                        stream.backUp(1);
                    }
                }
            }

            if (modeCfg.strikethrough) {
                if (ch === '~' && stream.eatWhile(ch)) {
                    if (state.strikethrough) {// Remove strikethrough
                        if (modeCfg.highlightFormatting) state.formatting = "strikethrough";
                        var t = getType(state);
                        state.strikethrough = false;
                        return t;
                    } else if (stream.match(/^[^\s]/, false)) {// Add strikethrough
                        state.strikethrough = true;
                        if (modeCfg.highlightFormatting) state.formatting = "strikethrough";
                        return getType(state);
                    }
                } else if (ch === ' ') {
                    if (stream.match(/^~~/, true)) { // Probably surrounded by space
                        if (stream.peek() === ' ') { // Surrounded by spaces, ignore
                            return getType(state);
                        } else { // Not surrounded by spaces, back up pointer
                            stream.backUp(2);
                        }
                    }
                }
            }

            if (ch === ' ') {
                if (stream.match(/ +$/, false)) {
                    state.trailingSpace++;
                } else if (state.trailingSpace) {
                    state.trailingSpaceNewLine = true;
                }
            }

            return getType(state);
        }

        function linkInline(stream, state) {
            var ch = stream.next();

            if (ch === ">") {
                state.f = state.inline = inlineNormal;
                if (modeCfg.highlightFormatting) state.formatting = "link";
                var type = getType(state);
                if (type){
                    type += " ";
                } else {
                    type = "";
                }
                return type + tokenTypes.linkInline;
            }

            stream.match(/^[^>]+/, true);

            return tokenTypes.linkInline;
        }

        function linkHref(stream, state) {
            // Check if space, and return NULL if so (to avoid marking the space)
            if(stream.eatSpace()){
                return null;
            }
            var ch = stream.next();
            if (ch === '(' || ch === '[') {
                state.f = state.inline = getLinkHrefInside(ch === "(" ? ")" : "]", 0);
                if (modeCfg.highlightFormatting) state.formatting = "link-string";
                state.linkHref = true;
                return getType(state);
            }
            return 'error';
        }

        var linkRE = {
            ")": /^(?:[^\\\(\)]|\\.|\((?:[^\\\(\)]|\\.)*\))*?(?=\))/,
            "]": /^(?:[^\\\[\]]|\\.|\[(?:[^\\\[\\]]|\\.)*\])*?(?=\])/
        }

        function getLinkHrefInside(endChar) {
            return function(stream, state) {
                var ch = stream.next();

                if (ch === endChar) {
                    state.f = state.inline = inlineNormal;
                    if (modeCfg.highlightFormatting) state.formatting = "link-string";
                    var returnState = getType(state);
                    state.linkHref = false;
                    return returnState;
                }

                stream.match(linkRE[endChar])
                state.linkHref = true;
                return getType(state);
            };
        }

        function footnoteLink(stream, state) {
            if (stream.match(/^([^\]\\]|\\.)*\]:/, false)) {
                state.f = footnoteLinkInside;
                stream.next(); // Consume [
                if (modeCfg.highlightFormatting) state.formatting = "link";
                state.linkText = true;
                return getType(state);
            }
            return switchInline(stream, state, inlineNormal);
        }

        function footnoteLinkInside(stream, state) {
            if (stream.match(/^\]:/, true)) {
                state.f = state.inline = footnoteUrl;
                if (modeCfg.highlightFormatting) state.formatting = "link";
                var returnType = getType(state);
                state.linkText = false;
                return returnType;
            }

            stream.match(/^([^\]\\]|\\.)+/, true);

            return tokenTypes.linkText;
        }

        function footnoteUrl(stream, state) {
            // Check if space, and return NULL if so (to avoid marking the space)
            if(stream.eatSpace()){
                return null;
            }
            // Match URL
            stream.match(/^[^\s]+/, true);
            // Check for link title
            if (stream.peek() === undefined) { // End of line, set flag to check next line
                state.linkTitle = true;
            } else { // More content on line, check if link title
                stream.match(/^(?:\s+(?:"(?:[^"\\]|\\\\|\\.)+"|'(?:[^'\\]|\\\\|\\.)+'|\((?:[^)\\]|\\\\|\\.)+\)))?/, true);
            }
            state.f = state.inline = inlineNormal;
            return tokenTypes.linkHref + " url";
        }

        var mode = {
            startState: function() {
                return {
                    f: blockNormal,

                    prevLine: null,
                    thisLine: null,

                    block: blockNormal,
                    htmlState: null,
                    indentation: 0,

                    inline: inlineNormal,
                    text: handleText,

                    formatting: false,
                    linkText: false,
                    linkHref: false,
                    linkTitle: false,
                    code: 0,
                    em: false,
                    strong: false,
                    header: 0,
                    hr: false,
                    taskList: false,
                    list: false,
                    listStack: [],
                    quote: 0,
                    trailingSpace: 0,
                    trailingSpaceNewLine: false,
                    strikethrough: false,
                    fencedChars: null
                };
            },

            copyState: function(s) {
                return {
                    f: s.f,

                    prevLine: s.prevLine,
                    thisLine: s.thisLine,

                    block: s.block,
                    htmlState: s.htmlState && CodeMirror.copyState(htmlMode, s.htmlState),
                    indentation: s.indentation,

                    localMode: s.localMode,
                    localState: s.localMode ? CodeMirror.copyState(s.localMode, s.localState) : null,

                    inline: s.inline,
                    text: s.text,
                    formatting: false,
                    linkTitle: s.linkTitle,
                    code: s.code,
                    em: s.em,
                    strong: s.strong,
                    strikethrough: s.strikethrough,
                    header: s.header,
                    hr: s.hr,
                    taskList: s.taskList,
                    list: s.list,
                    listStack: s.listStack.slice(0),
                    quote: s.quote,
                    indentedCode: s.indentedCode,
                    trailingSpace: s.trailingSpace,
                    trailingSpaceNewLine: s.trailingSpaceNewLine,
                    md_inside: s.md_inside,
                    fencedChars: s.fencedChars
                };
            },

            token: function(stream, state) {

                // Reset state.formatting
                state.formatting = false;

                if (stream != state.thisLine) {
                    var forceBlankLine = state.header || state.hr;

                    // Reset state.header and state.hr
                    state.header = 0;
                    state.hr = false;

                    if (stream.match(/^\s*$/, true) || forceBlankLine) {
                        blankLine(state);
                        if (!forceBlankLine) return null
                        state.prevLine = null
                    }

                    state.prevLine = state.thisLine
                    state.thisLine = stream

                    // Reset state.taskList
                    state.taskList = false;

                    // Reset state.trailingSpace
                    state.trailingSpace = 0;
                    state.trailingSpaceNewLine = false;

                    state.f = state.block;
                    var indentation = stream.match(/^\s*/, true)[0].replace(/\t/g, '    ').length;
                    state.indentationDiff = Math.min(indentation - state.indentation, 4);
                    state.indentation = state.indentation + state.indentationDiff;
                    if (indentation > 0) return null;
                }
                return state.f(stream, state);
            },

            innerMode: function(state) {
                if (state.block == htmlBlock) return {state: state.htmlState, mode: htmlMode};
                if (state.localState) return {state: state.localState, mode: state.localMode};
                return {state: state, mode: mode};
            },

            blankLine: blankLine,

            getType: getType,

            closeBrackets: "()[]{}''\"\"``",
            fold: "markdown"
        };
        return mode;
    }, "xml");

    CodeMirror.defineMIME("text/x-markdown", "markdown");

});
    <?php
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}


function resource_b5224402a7c86bd91173aa4a45d1b751() {
    ob_start(); ?>
    @import url('https://fonts.googleapis.com/css?family=Lato:300,400,400i,700,900&subset=latin-ext');
/* Used by prismjs */
@import url('https://fonts.googleapis.com/css?family=Cousine&subset=latin-ext');

/* ------------------------------------------------------------------------------------------------------------------
DEFAULTS
------------------------------------------------------------------------------------------------------------------ */
html,body,div,span,applet,object,iframe,h1,h2,h3,h4,h5,h6,p,blockquote,pre,a,abbr,
acronym,address,big,cite,code,del,dfn,em,img,ins,kbd,q,s,samp,small,strike,strong,
sub,sup,tt,var,dl,dt,dd,ol,ul,li,fieldset,form,label,legend,table,caption,tbody,
tfoot,thead,tr,th,td {
    margin: 0;
    padding: 0;
    border: 0;
    outline: 0;
    font-weight: inherit;
    font-style: inherit;
    font-family: 'Lato', sans-serif;
    font-size: 100%;
    vertical-align: baseline;
    box-sizing: border-box;
}
html {
    overflow-x: hidden;
    height: 100%;
}
body {
    line-height: 1;
    color: #000;
    background: #1d2021;
    height: 100%;
    margin-top: 30px;
}
ol, ul {
    list-style: none;
}
table {
    border-collapse: separate;
    border-spacing: 0;
    vertical-align: middle;
}
caption, th, td {
    text-align: left;
    font-weight: normal;
    vertical-align: middle;
}
a img {
    border: none;
}
hr {
    border: 0;
    border-bottom: 1px dashed #3f3f3f;
}

/* ------------------------------------------------------------------------------------------------------------------
FONTS DEFAULTS
------------------------------------------------------------------------------------------------------------------ */
body,
td,
textarea
{
    line-height: 1.6;
    font-size: 1em;
    color: #fff;
}
a {
    color: #fff;
    text-decoration: none;
}
a:hover {
    color: #80c9ff;
}
h1, h2, h3, h4, h5, h6 {
    font-weight: bold;
}






.content .breadcrumb {
    margin: 0 -15px 20px -15px !important;
    font-size: .9em;
    padding: 3px 15px;
    list-style: none;
}

.breadcrumb .active a {
    color: #80c9ff;
}
.breadcrumb>li {
    display: inline-block;
}
.breadcrumb>li+li:before {
    content: "/\00a0";
    padding: 0 5px;
    color: #ccc;
}

/* ------------------------------------------------------------------------------------------------------------------
HEADER
------------------------------------------------------------------------------------------------------------------ */
.header {
    padding: 5px 10px;
    color: #fff;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 9999;
    background: #fff;
    clear: both;
}

.header h2 {
    float: left;
    color: #000;
}

.user-actions {
    float: right;
    margin-right: 20px;
}

.user-actions a {
    color: #000;
}

/* ------------------------------------------------------------------------------------------------------------------
NAV
------------------------------------------------------------------------------------------------------------------ */
.nav {
    font-size: 0.9em;
}
.nav-inner {
    padding: 2rem;
}
.nav-inner > ul ul {
    margin-left: 15px;
}
.nav-inner a {
    box-sizing: border-box;
    position: relative;
    display: block;
    padding-top: 1px;
    padding-bottom: 1px;
    color: #737c84;
}
.nav-inner h2 {
    margin-top: 20px;
    color: #fff;
    font-size: 1.2rem;
}
.nav-inner h4 {
    text-transform: uppercase;
    font-size: 0.9em;
    font-weight: bold;
}
.nav-inner h5 {
    font-weight: normal;
    font-size: 0.9em;
    padding-left: 10px;
}
.nav-inner h6 {
    font-weight: bold !important;
}

.nav-inner > h1:first-child {
    margin-bottom: 10px;
}

#page-toc {
    background: #2f99f1;
    margin-left: -2rem;
    margin-right: -1.5rem;
    padding: .5rem 1rem .5rem 0;
    margin-bottom: 1rem;
    text-align: right;
    margin-top: .8rem;
}
#page-toc a {
    color: #a7d7ff;
    line-height: 16px;
    display: block;
    padding: 4px 0;
}
#page-toc .toc-active a, #page-toc a:hover {
    color: #fff
}
#page-toc .toc-h2 {
    padding-left: 10px;
}
#page-toc .toc-h3 {
    padding-left: 20px;
}

@media (max-width: 480px) {
    .nav {
        padding: 20px;
        border-bottom: solid 1px #dfe2e7;
    }
}
@media (max-width: 768px) {
    .nav {
        display: none;
    }
}
@media (min-width: 768px) {
    .container {
        padding-left: 270px;
    }
    .nav {
        left: 0;
        top: 40px;
        bottom: 0;
        width: 260px;
        position: fixed;
        overflow-y: auto;
        background: #000;
    }
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
        cursor: pointer;
    }
    ::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, .15);
        -webkit-border-radius: 8px;
        border-radius: 8px;
    }
}



/* ----------------------------------------------------------------------------
 * Content styling
 */
.content p,
.content ul,
.content ol,
.content h1,
.content h2,
.content h3,
.content h4,
.content h5,
.content h6,
.content pre:not([class*="language-"]),
.content blockquote {
    padding: 10px 0;
    box-sizing: border-box;
}

pre.CodeMirror-line {
    padding: 0 !important;
}

.content pre {
    font-family: Menlo, monospace;
    margin-bottom: 1em;
}
.content ol > li {
    list-style-type: decimal;
}
.content ul,
.content ol {
    margin-left: 20px;
}
.content ul > li {
    position: relative;
    list-style-type: disc;
}
.content li > :first-child {
    padding-top: 0;
}
.content strong,
.content b {
    font-weight: bold;
}
.content i,
.content em {
    font-style: italic;
}
.content code {
    font-family: Menlo, monospace;
    padding: 1px 3px;
    font-size: 0.95em;
    color: #fd5b95;
}
.content pre {
    max-height: 30rem;
}
.content pre > code {
    display: block;
    background: transparent;
    font-size: 1em;
    letter-spacing: -1px;
}
/*.content blockquote :first-child {*/
/*padding-top: 0;*/
/*}*/
/*.content blockquote :last-child {*/
/*padding-bottom: 0;*/
/*}*/
.content table {
    margin-top: 10px;
    margin-bottom: 10px;
    padding: 0;
    border-collapse: collapse;
    clear: both;
}
.content table tr {
    border-top: 1px solid #ccc;
    background-color: #fff;
    margin: 0;
    padding: 0;
}
.content table tr :nth-child(2n) {
    background-color: #f8f8f8;
}
.content table tr th {
    text-align: auto;
    font-weight: bold;
    border: 1px solid #ccc;
    margin: 0;
    padding: 6px 13px;
}
.content table tr td {
    text-align: auto;
    border: 1px solid #ccc;
    margin: 0;
    padding: 6px 13px;
}
.content table tr th :first-child,
.content table tr td :first-child {
    margin-top: 0;
}
.content table tr th :last-child,
.content table tr td :last-child {
    margin-bottom: 0;
}
/* ----------------------------------------------------------------------------
 * Content
 */
.content-root {
    min-height: 90%;
    position: relative;
}
.content {
    padding-top: 30px;
    padding-bottom: 40px;
    padding-left: 40px;
    padding-right: 40px;
    zoom: 1;
    max-width: 800px;
}
.edit-mode .content {
    max-width: 100%;
}
.CodeMirror {
    height: auto !important;
    padding: 20px;
}
.content:before,
.content:after {
    content: "";
    display: table;
}
.content:after {
    clear: both;
}
.content blockquote {
    color: #ffffff;
    text-shadow: 0 1px 0 rgba(255,255,255,0.5);
    background: #e06262;
    padding-left: 1rem;
}

.content h1 {
    font-weight: 300;
    font-size: 3rem;
    letter-spacing: 1px;
}

.content h2 {
    font-size: 2rem;
    font-weight: 300;
    color: #e8e8e8;
}
.content h3 {
    font-size: 1.5rem;
    font-weight: 300;
    color: #e8e8e8;
}

.content h4 {
    font-size: 1.2rem;
    font-weight: 400;
}

@media (max-width: 768px) {
    .content h4,
    .content h5,
    .content .small-heading {
        padding-top: 20px;
    }
}
@media (max-width: 480px) {
    .content {
        padding: 20px;
        padding-top: 40px;
    }
    .content h4,
    .content h5,
    .content .small-heading {
        padding-top: 10px;
    }
}

.container, .content, .content-inner {
    min-height: 100%;
}

.editor-textarea {
    width: 100%;
    height: 80%;
    font-family: Consolas, Monaco, 'Andale Mono', monospace;
    color: rgb(248, 248, 242);
    font-weight: normal;
    padding: 10px;
    margin: 0px;
    width: 700px;
    height: 604px;
    background: rgb(39, 40, 34);
    outline: 0;
}

/* ------------------------------------------------------------------------------------------------------------------
FOOTER
------------------------------------------------------------------------------------------------------------------ */
.footer {
    padding: 20px;
    text-align: right;
}
.footer .themes {
    display: inline-block;
}
.footer .copyrights {
    display: inline-block;
    color: gray;
    font-size: 11px;
}

/* ------------------------------------------------------------------------------------------------------------------
LOGIN PAGE
------------------------------------------------------------------------------------------------------------------ */
.login-page {
    background: #33332F;
}

.login-page form {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 260px;
    min-height: 200px;
    margin: -150px 0 0 -150px;
    text-align: center;
    /*background: #ef8d31;*/
    background: rgb(177, 199, 57);
    border-bottom: 8px solid #272727;
    padding: 20px;
}

.login-page .form-group {
    margin-bottom: 10px;
}

.login-page .form-group.checkbox {
    text-align: left;
}

.login-page input[type=text], .login-page input[type=password] {
    border: 0;
    width: 100%;
    padding: 10px 15px;
    box-sizing: border-box;
}

.login-page button {
    width: 100%;
    border: 0;
    background: #353535;
    color: #fff;
    padding: 10px;
}

.code-action-download {
    background: rgb(47, 153, 241);
    top: -8px;
    position: relative;
    color: #ffffff;
    text-transform: uppercase;
    font-size: 10px;
    height: 25px;
    display: inline-block;
    padding: 6px 6px;
}
.code-action-download:hover {
    /*background: rgba(255, 255, 255, 0.5);*/
    color: #000;
}

/**
 * okaidia theme for JavaScript, CSS and HTML
 * Loosely based on Monokai textmate theme by http://www.monokai.nl/
 * @author ocodia
 */

code[class*="language-"],
pre[class*="language-"] {
    color: #f8f8f2;
    background: none;
    text-shadow: 0 1px rgba(0, 0, 0, 0.3);
    font-family: Consolas, Monaco, 'Andale Mono', 'Ubuntu Mono', monospace;
    text-align: left;
    white-space: pre;
    word-spacing: normal;
    word-break: normal;
    word-wrap: normal;
    line-height: 1.5;

    -moz-tab-size: 4;
    -o-tab-size: 4;
    tab-size: 4;

    -webkit-hyphens: none;
    -moz-hyphens: none;
    -ms-hyphens: none;
    hyphens: none;
}

code[class*="language-"] {
    padding: 1rem;
}

/* Code blocks */
pre[class*="language-"] {
    padding: 1em;
    margin: .5em 0;
    overflow: auto;
    border-radius: 0.3em;
}

:not(pre) > code[class*="language-"],
pre[class*="language-"] {
    background: #272822;
}

/* Inline code */
:not(pre) > code[class*="language-"] {
    padding: .1em;
    border-radius: .3em;
    white-space: normal;
}

.token.comment,
.token.prolog,
.token.doctype,
.token.cdata {
    color: slategray;
    font-family: 'Cousine', monospace;
}

.token.comment {
    font-size: 14px;
}

.token.punctuation {
    color: #f8f8f2;
}

.namespace {
    opacity: .7;
}

.token.property,
.token.tag,
.token.constant,
.token.symbol,
.token.deleted {
    color: #f92672;
}

.token.boolean,
.token.number {
    color: #ae81ff;
}

.token.selector,
.token.attr-name,
.token.string,
.token.char,
.token.builtin,
.token.inserted {
    color: #a6e22e;
}

.token.operator,
.token.entity,
.token.url,
.language-css .token.string,
.style .token.string,
.token.variable {
    color: #f8f8f2;
}

.token.atrule,
.token.attr-value,
.token.function {
    color: #e6db74;
}

.token.keyword {
    color: #66d9ef;
}

.token.regex,
.token.important {
    color: #fd971f;
}

.token.important,
.token.bold {
    font-weight: bold;
}
.token.italic {
    font-style: italic;
}

.token.entity {
    cursor: help;
}
    <?php
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}


function resource_26ea431450ddec82e12665459ea890a0() {
    ob_start(); ?>
    // CodeMirror, copyright (c) by Marijn Haverbeke and others
// Distributed under an MIT license: http://codemirror.net/LICENSE

(function(mod) {
    if (typeof exports == "object" && typeof module == "object") // CommonJS
        mod(require("../../lib/codemirror"));
    else if (typeof define == "function" && define.amd) // AMD
        define(["../../lib/codemirror"], mod);
    else // Plain browser env
        mod(CodeMirror);
})(function(CodeMirror) {
    "use strict";

    var listRE = /^(\s*)(>[> ]*|- \[[x ]\]\s|[*+-]\s|(\d+)([.)]))(\s*)/,
        emptyListRE = /^(\s*)(>[> ]*|- \[[x ]\]|[*+-]|(\d+)[.)])(\s*)$/,
        unorderedListRE = /[*+-]\s/;

    CodeMirror.commands.newlineAndIndentContinueMarkdownList = function(cm) {
        if (cm.getOption("disableInput")) return CodeMirror.Pass;
        var ranges = cm.listSelections(), replacements = [];
        for (var i = 0; i < ranges.length; i++) {
            var pos = ranges[i].head;
            var eolState = cm.getStateAfter(pos.line);
            var inList = eolState.list !== false;
            var inQuote = eolState.quote !== 0;

            var line = cm.getLine(pos.line), match = listRE.exec(line);
            if (!ranges[i].empty() || (!inList && !inQuote) || !match) {
                cm.execCommand("newlineAndIndent");
                return;
            }
            if (emptyListRE.test(line)) {
                cm.replaceRange("", {
                    line: pos.line, ch: 0
                }, {
                    line: pos.line, ch: pos.ch + 1
                });
                replacements[i] = "\n";
            } else {
                var indent = match[1], after = match[5];
                var bullet = unorderedListRE.test(match[2]) || match[2].indexOf(">") >= 0
                    ? match[2].replace("x", " ")
                    : (parseInt(match[3], 10) + 1) + match[4];

                replacements[i] = "\n" + indent + bullet + after;
            }
        }

        cm.replaceSelections(replacements);
    };
});
    <?php
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}


function resource_14d2f72548c683c8191c0bcc303e6f6d() {
    ob_start(); ?>
    /*!
 * toc - jQuery Table of Contents Plugin
 * v0.3.2
 * http://projects.jga.me/toc/
 * copyright Greg Allen 2014
 * MIT License
 */
!function(a){a.fn.smoothScroller=function(b){b=a.extend({},a.fn.smoothScroller.defaults,b);var c=a(this);return a(b.scrollEl).animate({scrollTop:c.offset().top-a(b.scrollEl).offset().top-b.offset},b.speed,b.ease,function(){var a=c.attr("id");a.length&&(history.pushState?history.pushState(null,null,"#"+a):document.location.hash=a),c.trigger("smoothScrollerComplete")}),this},a.fn.smoothScroller.defaults={speed:400,ease:"swing",scrollEl:"body,html",offset:0},a("body").on("click","[data-smoothscroller]",function(b){b.preventDefault();var c=a(this).attr("href");0===c.indexOf("#")&&a(c).smoothScroller()})}(jQuery),function(a){var b={};a.fn.toc=function(b){var c,d=this,e=a.extend({},jQuery.fn.toc.defaults,b),f=a(e.container),g=a(e.selectors,f),h=[],i=e.activeClass,j=function(b,c){if(e.smoothScrolling&&"function"==typeof e.smoothScrolling){b.preventDefault();var f=a(b.target).attr("href");e.smoothScrolling(f,e,c)}a("li",d).removeClass(i),a(b.target).parent().addClass(i)},k=function(){c&&clearTimeout(c),c=setTimeout(function(){for(var b,c=a(window).scrollTop(),f=Number.MAX_VALUE,g=0,j=0,k=h.length;k>j;j++){var l=Math.abs(h[j]-c);f>l&&(g=j,f=l)}a("li",d).removeClass(i),b=a("li:eq("+g+")",d).addClass(i),e.onHighlight(b)},50)};return e.highlightOnScroll&&(a(window).bind("scroll",k),k()),this.each(function(){var b=a(this),c=a(e.listType);g.each(function(d,f){var g=a(f);h.push(g.offset().top-e.highlightOffset);var i=e.anchorName(d,f,e.prefix);if(f.id!==i){a("<span/>").attr("id",i).insertBefore(g)}var l=a("<a/>").text(e.headerText(d,f,g)).attr("href","#"+i).bind("click",function(c){a(window).unbind("scroll",k),j(c,function(){a(window).bind("scroll",k)}),b.trigger("selected",a(this).attr("href"))}),m=a("<li/>").addClass(e.itemClass(d,f,g,e.prefix)).append(l);c.append(m)}),b.html(c)})},jQuery.fn.toc.defaults={container:"body",listType:"<ul/>",selectors:"h1,h2,h3",smoothScrolling:function(b,c,d){a(b).smoothScroller({offset:c.scrollToOffset}).on("smoothScrollerComplete",function(){d()})},scrollToOffset:0,prefix:"toc",activeClass:"toc-active",onHighlight:function(){},highlightOnScroll:!0,highlightOffset:100,anchorName:function(c,d,e){if(d.id.length)return d.id;var f=a(d).text().replace(/[^a-z0-9]/gi," ").replace(/\s+/g,"-").toLowerCase();if(b[f]){for(var g=2;b[f+g];)g++;f=f+"-"+g}return b[f]=!0,e+"-"+f},headerText:function(a,b,c){return c.text()},itemClass:function(a,b,c,d){return d+"-"+c[0].tagName.toLowerCase()}}}(jQuery);
    <?php
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}


function resource_40ac8d6295fe2816f6b11a741a29a262() {
    ob_start(); ?>
    /**
 * Prism: Lightweight, robust, elegant syntax highlighting
 * MIT license http://www.opensource.org/licenses/mit-license.php/
 * @author Lea Verou http://lea.verou.me
 */(function(){var e=/\blang(?:uage)?-(?!\*)(\w+)\b/i,t=self.Prism={util:{type:function(e){return Object.prototype.toString.call(e).match(/\[object (\w+)\]/)[1]},clone:function(e){var n=t.util.type(e);switch(n){case"Object":var r={};for(var i in e)e.hasOwnProperty(i)&&(r[i]=t.util.clone(e[i]));return r;case"Array":return e.slice()}return e}},languages:{extend:function(e,n){var r=t.util.clone(t.languages[e]);for(var i in n)r[i]=n[i];return r},insertBefore:function(e,n,r,i){i=i||t.languages;var s=i[e],o={};for(var u in s)if(s.hasOwnProperty(u)){if(u==n)for(var a in r)r.hasOwnProperty(a)&&(o[a]=r[a]);o[u]=s[u]}return i[e]=o},DFS:function(e,n){for(var r in e){n.call(e,r,e[r]);t.util.type(e)==="Object"&&t.languages.DFS(e[r],n)}}},highlightAll:function(e,n){var r=document.querySelectorAll('code[class*="language-"], [class*="language-"] code, code[class*="lang-"], [class*="lang-"] code');for(var i=0,s;s=r[i++];)t.highlightElement(s,e===!0,n)},highlightElement:function(r,i,s){var o,u,a=r;while(a&&!e.test(a.className))a=a.parentNode;if(a){o=(a.className.match(e)||[,""])[1];u=t.languages[o]}if(!u)return;r.className=r.className.replace(e,"").replace(/\s+/g," ")+" language-"+o;a=r.parentNode;/pre/i.test(a.nodeName)&&(a.className=a.className.replace(e,"").replace(/\s+/g," ")+" language-"+o);var f=r.textContent;if(!f)return;f=f.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/\u00a0/g," ");var l={element:r,language:o,grammar:u,code:f};t.hooks.run("before-highlight",l);if(i&&self.Worker){var c=new Worker(t.filename);c.onmessage=function(e){l.highlightedCode=n.stringify(JSON.parse(e.data),o);t.hooks.run("before-insert",l);l.element.innerHTML=l.highlightedCode;s&&s.call(l.element);t.hooks.run("after-highlight",l)};c.postMessage(JSON.stringify({language:l.language,code:l.code}))}else{l.highlightedCode=t.highlight(l.code,l.grammar,l.language);t.hooks.run("before-insert",l);l.element.innerHTML=l.highlightedCode;s&&s.call(r);t.hooks.run("after-highlight",l)}},highlight:function(e,r,i){return n.stringify(t.tokenize(e,r),i)},tokenize:function(e,n,r){var i=t.Token,s=[e],o=n.rest;if(o){for(var u in o)n[u]=o[u];delete n.rest}e:for(var u in n){if(!n.hasOwnProperty(u)||!n[u])continue;var a=n[u],f=a.inside,l=!!a.lookbehind,c=0;a=a.pattern||a;for(var h=0;h<s.length;h++){var p=s[h];if(s.length>e.length)break e;if(p instanceof i)continue;a.lastIndex=0;var d=a.exec(p);if(d){l&&(c=d[1].length);var v=d.index-1+c,d=d[0].slice(c),m=d.length,g=v+m,y=p.slice(0,v+1),b=p.slice(g+1),w=[h,1];y&&w.push(y);var E=new i(u,f?t.tokenize(d,f):d);w.push(E);b&&w.push(b);Array.prototype.splice.apply(s,w)}}}return s},hooks:{all:{},add:function(e,n){var r=t.hooks.all;r[e]=r[e]||[];r[e].push(n)},run:function(e,n){var r=t.hooks.all[e];if(!r||!r.length)return;for(var i=0,s;s=r[i++];)s(n)}}},n=t.Token=function(e,t){this.type=e;this.content=t};n.stringify=function(e,r,i){if(typeof e=="string")return e;if(Object.prototype.toString.call(e)=="[object Array]")return e.map(function(t){return n.stringify(t,r,e)}).join("");var s={type:e.type,content:n.stringify(e.content,r,i),tag:"span",classes:["token",e.type],attributes:{},language:r,parent:i};s.type=="comment"&&(s.attributes.spellcheck="true");t.hooks.run("wrap",s);var o="";for(var u in s.attributes)o+=u+'="'+(s.attributes[u]||"")+'"';return"<"+s.tag+' class="'+s.classes.join(" ")+'" '+o+">"+s.content+"</"+s.tag+">"};if(!self.document){self.addEventListener("message",function(e){var n=JSON.parse(e.data),r=n.language,i=n.code;self.postMessage(JSON.stringify(t.tokenize(i,t.languages[r])));self.close()},!1);return}var r=document.getElementsByTagName("script");r=r[r.length-1];if(r){t.filename=r.src;document.addEventListener&&!r.hasAttribute("data-manual")&&document.addEventListener("DOMContentLoaded",t.highlightAll)}})();;
Prism.languages.markup={comment:/&lt;!--[\w\W]*?-->/g,prolog:/&lt;\?.+?\?>/,doctype:/&lt;!DOCTYPE.+?>/,cdata:/&lt;!\[CDATA\[[\w\W]*?]]>/i,tag:{pattern:/&lt;\/?[\w:-]+\s*(?:\s+[\w:-]+(?:=(?:("|')(\\?[\w\W])*?\1|\w+))?\s*)*\/?>/gi,inside:{tag:{pattern:/^&lt;\/?[\w:-]+/i,inside:{punctuation:/^&lt;\/?/,namespace:/^[\w-]+?:/}},"attr-value":{pattern:/=(?:('|")[\w\W]*?(\1)|[^\s>]+)/gi,inside:{punctuation:/=|>|"/g}},punctuation:/\/?>/g,"attr-name":{pattern:/[\w:-]+/g,inside:{namespace:/^[\w-]+?:/}}}},entity:/&amp;#?[\da-z]{1,8};/gi};Prism.hooks.add("wrap",function(e){e.type==="entity"&&(e.attributes.title=e.content.replace(/&amp;/,"&"))});;
Prism.languages.css={comment:/\/\*[\w\W]*?\*\//g,atrule:{pattern:/@[\w-]+?.*?(;|(?=\s*{))/gi,inside:{punctuation:/[;:]/g}},url:/url\((["']?).*?\1\)/gi,selector:/[^\{\}\s][^\{\};]*(?=\s*\{)/g,property:/(\b|\B)[\w-]+(?=\s*:)/ig,string:/("|')(\\?.)*?\1/g,important:/\B!important\b/gi,ignore:/&(lt|gt|amp);/gi,punctuation:/[\{\};:]/g};Prism.languages.markup&&Prism.languages.insertBefore("markup","tag",{style:{pattern:/(&lt;|<)style[\w\W]*?(>|&gt;)[\w\W]*?(&lt;|<)\/style(>|&gt;)/ig,inside:{tag:{pattern:/(&lt;|<)style[\w\W]*?(>|&gt;)|(&lt;|<)\/style(>|&gt;)/ig,inside:Prism.languages.markup.tag.inside},rest:Prism.languages.css}}});;
Prism.languages.css.selector={pattern:/[^\{\}\s][^\{\}]*(?=\s*\{)/g,inside:{"pseudo-element":/:(?:after|before|first-letter|first-line|selection)|::[-\w]+/g,"pseudo-class":/:[-\w]+(?:\(.*\))?/g,"class":/\.[-:\.\w]+/g,id:/#[-:\.\w]+/g}};Prism.languages.insertBefore("css","ignore",{hexcode:/#[\da-f]{3,6}/gi,entity:/\\[\da-f]{1,8}/gi,number:/[\d%\.]+/g,"function":/(attr|calc|cross-fade|cycle|element|hsla?|image|lang|linear-gradient|matrix3d|matrix|perspective|radial-gradient|repeating-linear-gradient|repeating-radial-gradient|rgba?|rotatex|rotatey|rotatez|rotate3d|rotate|scalex|scaley|scalez|scale3d|scale|skewx|skewy|skew|steps|translatex|translatey|translatez|translate3d|translate|url|var)/ig});;
Prism.languages.clike={comment:{pattern:/(^|[^\\])(\/\*[\w\W]*?\*\/|(^|[^:])\/\/.*?(\r?\n|$))/g,lookbehind:!0},string:/("|')(\\?.)*?\1/g,"class-name":{pattern:/((?:(?:class|interface|extends|implements|trait|instanceof|new)\s+)|(?:catch\s+\())[a-z0-9_\.\\]+/ig,lookbehind:!0,inside:{punctuation:/(\.|\\)/}},keyword:/\b(if|else|while|do|for|return|in|instanceof|function|new|try|throw|catch|finally|null|break|continue)\b/g,"boolean":/\b(true|false)\b/g,"function":{pattern:/[a-z0-9_]+\(/ig,inside:{punctuation:/\(/}}, number:/\b-?(0x[\dA-Fa-f]+|\d*\.?\d+([Ee]-?\d+)?)\b/g,operator:/[-+]{1,2}|!|&lt;=?|>=?|={1,3}|(&amp;){1,2}|\|?\||\?|\*|\/|\~|\^|\%/g,ignore:/&(lt|gt|amp);/gi,punctuation:/[{}[\];(),.:]/g};
;
Prism.languages.javascript=Prism.languages.extend("clike",{keyword:/\b(var|let|if|else|while|do|for|return|in|instanceof|function|get|set|new|with|typeof|try|throw|catch|finally|null|break|continue)\b/g,number:/\b-?(0x[\dA-Fa-f]+|\d*\.?\d+([Ee]-?\d+)?|NaN|-?Infinity)\b/g});Prism.languages.insertBefore("javascript","keyword",{regex:{pattern:/(^|[^/])\/(?!\/)(\[.+?]|\\.|[^/\r\n])+\/[gim]{0,3}(?=\s*($|[\r\n,.;})]))/g,lookbehind:!0}});Prism.languages.markup&&Prism.languages.insertBefore("markup","tag",{script:{pattern:/(&lt;|<)script[\w\W]*?(>|&gt;)[\w\W]*?(&lt;|<)\/script(>|&gt;)/ig,inside:{tag:{pattern:/(&lt;|<)script[\w\W]*?(>|&gt;)|(&lt;|<)\/script(>|&gt;)/ig,inside:Prism.languages.markup.tag.inside},rest:Prism.languages.javascript}}});;
Prism.languages.java=Prism.languages.extend("clike",{keyword:/\b(abstract|continue|for|new|switch|assert|default|goto|package|synchronized|boolean|do|if|private|this|break|double|implements|protected|throw|byte|else|import|public|throws|case|enum|instanceof|return|transient|catch|extends|int|short|try|char|final|interface|static|void|class|finally|long|strictfp|volatile|const|float|native|super|while)\b/g,number:/\b0b[01]+\b|\b0x[\da-f]*\.?[\da-fp\-]+\b|\b\d*\.?\d+[e]?[\d]*[df]\b|\W\d*\.?\d+\b/gi,operator:{pattern:/([^\.]|^)([-+]{1,2}|!|=?&lt;|=?&gt;|={1,2}|(&amp;){1,2}|\|?\||\?|\*|\/|%|\^|(&lt;){2}|($gt;){2,3}|:|~)/g,lookbehind:!0}});;
Prism.languages.php=Prism.languages.extend("clike",{keyword:/\b(and|or|xor|array|as|break|case|cfunction|class|const|continue|declare|default|die|do|else|elseif|enddeclare|endfor|endforeach|endif|endswitch|endwhile|extends|for|foreach|function|include|include_once|global|if|new|return|static|switch|use|require|require_once|var|while|abstract|interface|public|implements|extends|private|protected|parent|static|throw|null|echo|print|trait|namespace|use|final|yield|goto|instanceof|finally|try|catch)\b/ig, constant:/\b[A-Z0-9_]{2,}\b/g});Prism.languages.insertBefore("php","keyword",{delimiter:/(\?>|&lt;\?php|&lt;\?)/ig,variable:/(\$\w+)\b/ig,"package":{pattern:/(\\|namespace\s+|use\s+)[\w\\]+/g,lookbehind:!0,inside:{punctuation:/\\/}}});Prism.languages.insertBefore("php","operator",{property:{pattern:/(->)[\w]+/g,lookbehind:!0}}); Prism.languages.markup&&(Prism.hooks.add("before-highlight",function(a){"php"===a.language&&(a.tokenStack=[],a.code=a.code.replace(/(?:&lt;\?php|&lt;\?|<\?php|<\?)[\w\W]*?(?:\?&gt;|\?>)/ig,function(b){a.tokenStack.push(b);return"{{{PHP"+a.tokenStack.length+"}}}"}))}),Prism.hooks.add("after-highlight",function(a){if("php"===a.language){for(var b=0,c;c=a.tokenStack[b];b++)a.highlightedCode=a.highlightedCode.replace("{{{PHP"+(b+1)+"}}}",Prism.highlight(c,a.grammar,"php"));a.element.innerHTML=a.highlightedCode}}), Prism.hooks.add("wrap",function(a){"php"===a.language&&"markup"===a.type&&(a.content=a.content.replace(/(\{\{\{PHP[0-9]+\}\}\})/g,'<span class="token php">$1</span>'))}),Prism.languages.insertBefore("php","comment",{markup:{pattern:/(&lt;|<)[^?]\/?(.*?)(>|&gt;)/g,inside:Prism.languages.markup},php:/\{\{\{PHP[0-9]+\}\}\}/g}));;
Prism.languages.insertBefore("php","variable",{"this":/\$this/g,global:/\$_?(GLOBALS|SERVER|GET|POST|FILES|REQUEST|SESSION|ENV|COOKIE|HTTP_RAW_POST_DATA|argc|argv|php_errormsg|http_response_header)/g,scope:{pattern:/\b[\w\\]+::/g,inside:{keyword:/(static|self|parent)/,punctuation:/(::|\\)/}}});;
Prism.languages.coffeescript=Prism.languages.extend("javascript",{"block-comment":/([#]{3}\s*\r?\n(.*\s*\r*\n*)\s*?\r?\n[#]{3})/g,comment:/(\s|^)([#]{1}[^#^\r^\n]{2,}?(\r?\n|$))/g,keyword:/\b(this|window|delete|class|extends|namespace|extend|ar|let|if|else|while|do|for|each|of|return|in|instanceof|new|with|typeof|try|catch|finally|null|undefined|break|continue)\b/g});Prism.languages.insertBefore("coffeescript","keyword",{"function":{pattern:/[a-z|A-z]+\s*[:|=]\s*(\([.|a-z\s|,|:|{|}|\"|\'|=]*\))?\s*-&gt;/gi,inside:{"function-name":/[_?a-z-|A-Z-]+(\s*[:|=])| @[_?$?a-z-|A-Z-]+(\s*)| /g,operator:/[-+]{1,2}|!|=?&lt;|=?&gt;|={1,2}|(&amp;){1,2}|\|?\||\?|\*|\//g}},"attr-name":/[_?a-z-|A-Z-]+(\s*:)| @[_?$?a-z-|A-Z-]+(\s*)| /g});;
Prism.languages.scss=Prism.languages.extend("css",{comment:{pattern:/(^|[^\\])(\/\*[\w\W]*?\*\/|\/\/.*?(\r?\n|$))/g,lookbehind:!0},atrule:/@[\w-]+(?=\s+(\(|\{|;))/gi,url:/([-a-z]+-)*url(?=\()/gi,selector:/([^@;\{\}\(\)]?([^@;\{\}\(\)]|&amp;|\#\{\$[-_\w]+\})+)(?=\s*\{(\}|\s|[^\}]+(:|\{)[^\}]+))/gm});Prism.languages.insertBefore("scss","atrule",{keyword:/@(if|else if|else|for|each|while|import|extend|debug|warn|mixin|include|function|return)|(?=@for\s+\$[-_\w]+\s)+from/i});Prism.languages.insertBefore("scss","property",{variable:/((\$[-_\w]+)|(#\{\$[-_\w]+\}))/i});Prism.languages.insertBefore("scss","ignore",{placeholder:/%[-_\w]+/i,statement:/\B!(default|optional)\b/gi,"boolean":/\b(true|false)\b/g,"null":/\b(null)\b/g,operator:/\s+([-+]{1,2}|={1,2}|!=|\|?\||\?|\*|\/|\%)\s+/g});
;
Prism.languages.bash=Prism.languages.extend("clike",{comment:{pattern:/(^|[^"{\\])(#.*?(\r?\n|$))/g,lookbehind:!0},string:{pattern:/("|')(\\?[\s\S])*?\1/g,inside:{property:/\$([a-zA-Z0-9_#\?\-\*!@]+|\{[^\}]+\})/g}},keyword:/\b(if|then|else|elif|fi|for|break|continue|while|in|case|function|select|do|done|until|echo|exit|return|set|declare)\b/g});Prism.languages.insertBefore("bash","keyword",{property:/\$([a-zA-Z0-9_#\?\-\*!@]+|\{[^}]+\})/g});Prism.languages.insertBefore("bash","comment",{important:/(^#!\s*\/bin\/bash)|(^#!\s*\/bin\/sh)/g});
;
Prism.languages.c=Prism.languages.extend("clike",{keyword:/\b(asm|typeof|inline|auto|break|case|char|const|continue|default|do|double|else|enum|extern|float|for|goto|if|int|long|register|return|short|signed|sizeof|static|struct|switch|typedef|union|unsigned|void|volatile|while)\b/g,operator:/[-+]{1,2}|!=?|&lt;{1,2}=?|&gt;{1,2}=?|\-&gt;|={1,2}|\^|~|%|(&amp;){1,2}|\|?\||\?|\*|\//g});Prism.languages.insertBefore("c","keyword",{property:/#\s*[a-zA-Z]+/g});
;
Prism.languages.cpp=Prism.languages.extend("c",{keyword:/\b(alignas|alignof|asm|auto|bool|break|case|catch|char|char16_t|char32_t|class|compl|const|constexpr|const_cast|continue|decltype|default|delete|delete\[\]|do|double|dynamic_cast|else|enum|explicit|export|extern|float|for|friend|goto|if|inline|int|long|mutable|namespace|new|new\[\]|noexcept|nullptr|operator|private|protected|public|register|reinterpret_cast|return|short|signed|sizeof|static|static_assert|static_cast|struct|switch|template|this|thread_local|throw|try|typedef|typeid|typename|union|unsigned|using|virtual|void|volatile|wchar_t|while)\b/g,
    operator:/[-+]{1,2}|!=?|&lt;{1,2}=?|&gt;{1,2}=?|\-&gt;|:{1,2}|={1,2}|\^|~|%|(&amp;){1,2}|\|?\||\?|\*|\/|\b(and|and_eq|bitand|bitor|not|not_eq|or|or_eq|xor|xor_eq)\b/g});
;
Prism.languages.python={comment:{pattern:/(^|[^\\])#.*?(\r?\n|$)/g,lookbehind:!0},string: /("|')(\\?.)*?\1/g,keyword:/\b(as|assert|break|class|continue|def|del|elif|else|except|exec|finally|for|from|global|if|import|in|is|lambda|pass|print|raise|return|try|while|with|yield)\b/g,boolean:/\b(True|False)\b/g,number:/\b-?(0x)?\d*\.?[\da-f]+\b/g,operator:/[-+]{1,2}|=?&lt;|=?&gt;|!|={1,2}|(&){1,2}|(&amp;){1,2}|\|?\||\?|\*|\/|~|\^|%|\b(or|and|not)\b/g,ignore:/&(lt|gt|amp);/gi,punctuation:/[{}[\];(),.:]/g};;
Prism.languages.sql={comment:{pattern:/(^|[^\\])(\/\*[\w\W]*?\*\/|((--)|(\/\/)).*?(\r?\n|$))/g,lookbehind:!0},string: /("|')(\\?.)*?\1/g,keyword:/\b(ACTION|ADD|AFTER|ALGORITHM|ALTER|ANALYZE|APPLY|AS|AS|ASC|AUTHORIZATION|BACKUP|BDB|BEGIN|BERKELEYDB|BIGINT|BINARY|BIT|BLOB|BOOL|BOOLEAN|BREAK|BROWSE|BTREE|BULK|BY|CALL|CASCADE|CASCADED|CASE|CHAIN|CHAR VARYING|CHARACTER VARYING|CHECK|CHECKPOINT|CLOSE|CLUSTERED|COALESCE|COLUMN|COLUMNS|COMMENT|COMMIT|COMMITTED|COMPUTE|CONNECT|CONSISTENT|CONSTRAINT|CONTAINS|CONTAINSTABLE|CONTINUE|CONVERT|CREATE|CROSS|CURRENT|CURRENT_DATE|CURRENT_TIME|CURRENT_TIMESTAMP|CURRENT_USER|CURSOR|DATA|DATABASE|DATABASES|DATETIME|DBCC|DEALLOCATE|DEC|DECIMAL|DECLARE|DEFAULT|DEFINER|DELAYED|DELETE|DENY|DESC|DESCRIBE|DETERMINISTIC|DISABLE|DISCARD|DISK|DISTINCT|DISTINCTROW|DISTRIBUTED|DO|DOUBLE|DOUBLE PRECISION|DROP|DUMMY|DUMP|DUMPFILE|DUPLICATE KEY|ELSE|ENABLE|ENCLOSED BY|END|ENGINE|ENUM|ERRLVL|ERRORS|ESCAPE|ESCAPED BY|EXCEPT|EXEC|EXECUTE|EXIT|EXPLAIN|EXTENDED|FETCH|FIELDS|FILE|FILLFACTOR|FIRST|FIXED|FLOAT|FOLLOWING|FOR|FOR EACH ROW|FORCE|FOREIGN|FREETEXT|FREETEXTTABLE|FROM|FULL|FUNCTION|GEOMETRY|GEOMETRYCOLLECTION|GLOBAL|GOTO|GRANT|GROUP|HANDLER|HASH|HAVING|HOLDLOCK|IDENTITY|IDENTITY_INSERT|IDENTITYCOL|IF|IGNORE|IMPORT|INDEX|INFILE|INNER|INNODB|INOUT|INSERT|INT|INTEGER|INTERSECT|INTO|INVOKER|ISOLATION LEVEL|JOIN|KEY|KEYS|KILL|LANGUAGE SQL|LAST|LEFT|LIMIT|LINENO|LINES|LINESTRING|LOAD|LOCAL|LOCK|LONGBLOB|LONGTEXT|MATCH|MATCHED|MEDIUMBLOB|MEDIUMINT|MEDIUMTEXT|MERGE|MIDDLEINT|MODIFIES SQL DATA|MODIFY|MULTILINESTRING|MULTIPOINT|MULTIPOLYGON|NATIONAL|NATIONAL CHAR VARYING|NATIONAL CHARACTER|NATIONAL CHARACTER VARYING|NATIONAL VARCHAR|NATURAL|NCHAR|NCHAR VARCHAR|NEXT|NO|NO SQL|NOCHECK|NOCYCLE|NONCLUSTERED|NULLIF|NUMERIC|OF|OFF|OFFSETS|ON|OPEN|OPENDATASOURCE|OPENQUERY|OPENROWSET|OPTIMIZE|OPTION|OPTIONALLY|ORDER|OUT|OUTER|OUTFILE|OVER|PARTIAL|PARTITION|PERCENT|PIVOT|PLAN|POINT|POLYGON|PRECEDING|PRECISION|PREV|PRIMARY|PRINT|PRIVILEGES|PROC|PROCEDURE|PUBLIC|PURGE|QUICK|RAISERROR|READ|READS SQL DATA|READTEXT|REAL|RECONFIGURE|REFERENCES|RELEASE|RENAME|REPEATABLE|REPLICATION|REQUIRE|RESTORE|RESTRICT|RETURN|RETURNS|REVOKE|RIGHT|ROLLBACK|ROUTINE|ROWCOUNT|ROWGUIDCOL|ROWS?|RTREE|RULE|SAVE|SAVEPOINT|SCHEMA|SELECT|SERIAL|SERIALIZABLE|SESSION|SESSION_USER|SET|SETUSER|SHARE MODE|SHOW|SHUTDOWN|SIMPLE|SMALLINT|SNAPSHOT|SOME|SONAME|START|STARTING BY|STATISTICS|STATUS|STRIPED|SYSTEM_USER|TABLE|TABLES|TABLESPACE|TEMPORARY|TEMPTABLE|TERMINATED BY|TEXT|TEXTSIZE|THEN|TIMESTAMP|TINYBLOB|TINYINT|TINYTEXT|TO|TOP|TRAN|TRANSACTION|TRANSACTIONS|TRIGGER|TRUNCATE|TSEQUAL|TYPE|TYPES|UNBOUNDED|UNCOMMITTED|UNDEFINED|UNION|UNPIVOT|UPDATE|UPDATETEXT|USAGE|USE|USER|USING|VALUE|VALUES|VARBINARY|VARCHAR|VARCHARACTER|VARYING|VIEW|WAITFOR|WARNINGS|WHEN|WHERE|WHILE|WITH|WITH ROLLUP|WITHIN|WORK|WRITE|WRITETEXT)\b/gi,boolean:/\b(TRUE|FALSE|NULL)\b/gi,number:/\b-?(0x)?\d*\.?[\da-f]+\b/g,operator:/\b(ALL|AND|ANY|BETWEEN|EXISTS|IN|LIKE|NOT|OR|IS|UNIQUE|CHARACTER SET|COLLATE|DIV|OFFSET|REGEXP|RLIKE|SOUNDS LIKE|XOR)\b|[-+]{1}|!|=?&lt;|=?&gt;|={1}|(&amp;){1,2}|\|?\||\?|\*|\//gi,ignore:/&(lt|gt|amp);/gi,punctuation:/[;[\]()`,.]/g};;
Prism.languages.groovy=Prism.languages.extend("clike",{keyword:/\b(as|def|in|abstract|assert|boolean|break|byte|case|catch|char|class|const|continue|default|do|double|else|enum|extends|final|finally|float|for|goto|if|implements|import|instanceof|int|interface|long|native|new|package|private|protected|public|return|short|static|strictfp|super|switch|synchronized|this|throw|throws|transient|try|void|volatile|while)\b/g,string:/("""|''')[\W\w]*?\1|("|'|\/)[\W\w]*?\2/g,number:/\b0b[01_]+\b|\b0x[\da-f_]+(\.[\da-f_p\-]+)?\b|\b[\d_]+(\.[\d_]+[e]?[\d]*)?[glidf]\b|[\d_]+(\.[\d_]+)?\b/gi,operator:/={0,2}~|\?\.|\*?\.@|\.&amp;|\.(?=\w)|\.{2}(&lt;)?(?=\w)|-&gt;|\?:|[-+]{1,2}|!|&lt;=&gt;|(&gt;){1,3}|(&lt;){1,2}|={1,2}|(&amp;){1,2}|\|{1,2}|\?|\*{1,2}|\/|\^|%/g,punctuation:/\.+|[{}[\];(),:$]/g,annotation:/@\w+/});Prism.languages.insertBefore("groovy","punctuation",{"spock-block":/\b(setup|given|when|then|and|cleanup|expect|where):/g});Prism.hooks.add("wrap",function(e){if(e.language==="groovy"&&e.type==="string"){var t=e.content[0];if(t!="'"){e.content=Prism.highlight(e.content,{expression:{pattern:/([^\\])(\$(\{.*?\}|[\w\.]*))/,lookbehind:!0,inside:Prism.languages.groovy}});e.classes.push(t==="/"?"regex":"gstring")}}});;
Prism.languages.http={"request-line":{pattern:/^(POST|GET|PUT|DELETE|OPTIONS|PATCH|TRACE|CONNECT)\b\shttps?:\/\/\S+\sHTTP\/[0-9.]+/g,inside:{property:/^\b(POST|GET|PUT|DELETE|OPTIONS|PATCH|TRACE|CONNECT)\b/g,"attr-name":/:\w+/g}},"response-status":{pattern:/^HTTP\/1.[01] [0-9]+.*/g,inside:{property:/[0-9]+[A-Z\s-]+$/g}},keyword:/^[\w-]+:(?=.+)/gm};var httpLanguages={"application/json":Prism.languages.javascript,"application/xml":Prism.languages.markup,"text/xml":Prism.languages.markup,"text/html":Prism.languages.markup};for(var contentType in httpLanguages){if(httpLanguages[contentType]){var options={};options[contentType]={pattern:new RegExp("(content-type:\\s*"+contentType+"[\\w\\W]*?)\\n\\n[\\w\\W]*","gi"),lookbehind:true,inside:{rest:httpLanguages[contentType]}};Prism.languages.insertBefore("http","keyword",options)}}
;
/**
 * Original by Samuel Flores
 *
 * Adds the following new token classes:
 *      constant, builtin, variable, symbol, regex
 */Prism.languages.ruby=Prism.languages.extend("clike",{comment:/#[^\r\n]*(\r?\n|$)/g,keyword:/\b(alias|and|BEGIN|begin|break|case|class|def|define_method|defined|do|each|else|elsif|END|end|ensure|false|for|if|in|module|new|next|nil|not|or|raise|redo|require|rescue|retry|return|self|super|then|throw|true|undef|unless|until|when|while|yield)\b/g,builtin:/\b(Array|Bignum|Binding|Class|Continuation|Dir|Exception|FalseClass|File|Stat|File|Fixnum|Fload|Hash|Integer|IO|MatchData|Method|Module|NilClass|Numeric|Object|Proc|Range|Regexp|String|Struct|TMS|Symbol|ThreadGroup|Thread|Time|TrueClass)\b/,constant:/\b[A-Z][a-zA-Z_0-9]*[?!]?\b/g});Prism.languages.insertBefore("ruby","keyword",{regex:{pattern:/(^|[^/])\/(?!\/)(\[.+?]|\\.|[^/\r\n])+\/[gim]{0,3}(?=\s*($|[\r\n,.;})]))/g,lookbehind:!0},variable:/[@$]+\b[a-zA-Z_][a-zA-Z_0-9]*[?!]?\b/g,symbol:/:\b[a-zA-Z_][a-zA-Z_0-9]*[?!]?\b/g});;
Prism.languages.gherkin={comment:{pattern:/(^|[^\\])(\/\*[\w\W]*?\*\/|((#)|(\/\/)).*?(\r?\n|$))/g,lookbehind:true},string:/("|')(\\?.)*?\1/g,atrule:/\b(And|Given|When|Then|In order to|As an|I want to|As a)\b/g,keyword:/\b(Scenario Outline|Scenario|Feature|Background|Story)\b/g};
Prism.languages.csharp=Prism.languages.extend("clike",{keyword:/\b(abstract|as|base|bool|break|byte|case|catch|char|checked|class|const|continue|decimal|default|delegate|do|double|else|enum|event|explicit|extern|false|finally|fixed|float|for|foreach|goto|if|implicit|in|int|interface|internal|is|lock|long|namespace|new|null|object|operator|out|override|params|private|protected|public|readonly|ref|return|sbyte|sealed|short|sizeof|stackalloc|static|string|struct|switch|this|throw|true|try|typeof|uint|ulong|unchecked|unsafe|ushort|using|virtual|void|volatile|while|add|alias|ascending|async|await|descending|dynamic|from|get|global|group|into|join|let|orderby|partial|remove|select|set|value|var|where|yield)\b/g,string:/@?("|')(\\?.)*?\1/g,preprocessor:/^\s*#.*/gm,number:/\b-?(0x)?\d*\.?\d+\b/g});
Prism.languages.go=Prism.languages.extend("clike",{keyword:/\b(break|case|chan|const|continue|default|defer|else|fallthrough|for|func|go(to)?|if|import|interface|map|package|range|return|select|struct|switch|type|var)\b/g,builtin:/\b(bool|byte|complex(64|128)|error|float(32|64)|rune|string|u?int(8|16|32|64|)|uintptr|append|cap|close|complex|copy|delete|imag|len|make|new|panic|print(ln)?|real|recover)\b/g,'boolean':/\b(_|iota|nil|true|false)\b/g,operator:/([(){}\[\]]|[*\/%^!]=?|\+[=+]?|-[>=-]?|\|[=|]?|>[=>]?|&lt;(&lt;|[=-])?|==?|&amp;(&amp;|=|^=?)?|\.(\.\.)?|[,;]|:=?)/g,number:/\b(-?(0x[a-f\d]+|(\d+\.?\d*|\.\d+)(e[-+]?\d+)?)i?)\b/ig,string:/("|'|`)(\\?.|\r|\n)*?\1/g});delete Prism.languages.go['class-name'];;
Prism.hooks.add("after-highlight",function(e){var t=e.element.parentNode;if(!t||!/pre/i.test(t.nodeName)||t.className.indexOf("line-numbers")===-1){return}var n=1+e.code.split("\n").length;var r;lines=new Array(n);lines=lines.join("<span></span>");r=document.createElement("span");r.className="line-numbers-rows";r.innerHTML=lines;if(t.hasAttribute("data-start")){t.style.counterReset="linenumber "+(parseInt(t.getAttribute("data-start"),10)-1)}e.element.appendChild(r)})
;
    <?php
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}


function resource_de6111cfa3ef50cb533c22819f26c4d3() {
    ob_start(); ?>
    @import url('https://fonts.googleapis.com/css?family=Lato:300,400,400i,700,900&subset=latin-ext');

/* ------------------------------------------------------------------------------------------------------------------
DEFAULTS
------------------------------------------------------------------------------------------------------------------ */
html,body,div,span,applet,object,iframe,h1,h2,h3,h4,h5,h6,p,blockquote,pre,a,abbr,
acronym,address,big,cite,code,del,dfn,em,img,ins,kbd,q,s,samp,small,strike,strong,
sub,sup,tt,var,dl,dt,dd,ol,ul,li,fieldset,form,label,legend,table,caption,tbody,
tfoot,thead,tr,th,td {
    margin: 0;
    padding: 0;
    border: 0;
    outline: 0;
    font-weight: inherit;
    font-style: inherit;
    font-family: 'Lato', sans-serif;
    font-size: 100%;
    vertical-align: baseline;
    box-sizing: border-box;
}
html {
    overflow-x: hidden;
    height: 100%;
}
body {
    line-height: 1;
    color: #000;
    background: #ef6969;
    height: 100%;
    margin-top: 30px;
}
ol, ul {
    list-style: none;
}
table {
    border-collapse: separate;
    border-spacing: 0;
    vertical-align: middle;
}
caption, th, td {
    text-align: left;
    font-weight: normal;
    vertical-align: middle;
}
a img {
    border: none;
}
hr {
    border: 0;
    border-bottom: 1px dashed #ffb8b8;
}

/* ------------------------------------------------------------------------------------------------------------------
FONTS DEFAULTS
------------------------------------------------------------------------------------------------------------------ */
body,
td,
textarea
{
    line-height: 1.6;
    font-size: 1em;
    color: #fff;
}
a {
    color: #fff;
    text-decoration: none;
}
a:hover {
    color: #330606;
}
h1, h2, h3, h4, h5, h6 {
    font-weight: bold;
}






.content .breadcrumb {
    margin: 0 -15px 20px -15px !important;
    font-size: .9em;
    padding: 3px 15px;
    list-style: none;
    background-color: #ffb2b2;
}

.breadcrumb .active a {
    color: #da5252;
}
.breadcrumb>li {
    display: inline-block;
}
.breadcrumb>li+li:before {
    content: "/\00a0";
    padding: 0 5px;
    color: #ccc;
}

/* ------------------------------------------------------------------------------------------------------------------
HEADER
------------------------------------------------------------------------------------------------------------------ */
.header {
    padding: 5px 10px;
    color: #fff;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 9999;
    background: #330606;
    clear: both;
}

.header h2 {
    float: left;
}

.user-actions {
    float: right;
    margin-right: 20px;
}

/* ------------------------------------------------------------------------------------------------------------------
FOOTER
------------------------------------------------------------------------------------------------------------------ */
.footer {
    padding: 20px;
    text-align: right;
}
.footer .themes {
    display: inline-block;
}
.footer .copyrights {
    display: inline-block;
    color: gray;
    font-size: 11px;
}


/* ------------------------------------------------------------------------------------------------------------------
NAV
------------------------------------------------------------------------------------------------------------------ */
.nav {
    font-size: 0.9em;
}
.nav-inner {
    padding: 2rem;
}
.nav-inner > ul ul {
    margin-left: 15px;
}
.nav-inner a {
    box-sizing: border-box;
    position: relative;
    display: block;
    padding-top: 1px;
    padding-bottom: 1px;
}
.nav-inner h4 {
    text-transform: uppercase;
    font-size: 0.9em;
    font-weight: bold;
}
.nav-inner h5 {
    font-weight: normal;
    font-size: 0.9em;
    padding-left: 10px;
}
.nav-inner h6 {
    font-weight: bold !important;
}

.nav-inner > h1:first-child {
    margin-bottom: 10px;
}

#page-toc {
    background: #e06262;
    margin-left: -2rem;
    margin-right: -1.5rem;
    padding: .5rem 1rem .5rem 0;
    margin-bottom: 1rem;
    text-align: right;
    margin-top: .8rem;
}
#page-toc a {
    color: #fff;
}
#page-toc .toc-active a, #page-toc a:hover {
    color: #000;
}
#page-toc .toc-h2 {
    padding-left: 10px;
}
#page-toc .toc-h3 {
    padding-left: 20px;
}

@media (max-width: 480px) {
    .nav {
        padding: 20px;
        border-bottom: solid 1px #dfe2e7;
    }
}
@media (max-width: 768px) {
    .nav {
        display: none;
    }
}
@media (min-width: 768px) {
    .container {
        padding-left: 270px;
    }
    .nav {
        left: 0;
        top: 40px;
        bottom: 0;
        width: 260px;
        border-right: solid 1px #ff8383;
        position: fixed;
        overflow-y: auto;
    }
    .nav::-webkit-scrollbar {
        width: 8px;
        height: 15px;
        cursor: pointer;
    }
    .nav::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, .5);
        -webkit-border-radius: 8px;
        border-radius: 8px;
    }
    .nav::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, .5);
    }
}



/* ----------------------------------------------------------------------------
 * Content styling
 */
.content p,
.content ul,
.content ol,
.content h1,
.content h2,
.content h3,
.content h4,
.content h5,
.content h6,
.content pre:not([class*="language-"]),
.content blockquote {
    padding: 10px 0;
    box-sizing: border-box;
}
pre.CodeMirror-line {
    padding: 0 !important;
}
.edit-mode .content {
    max-width: 100%;
}
.CodeMirror {
    height: auto !important;
    padding: 20px;
}
.content pre {
    font-family: Menlo, monospace;
    margin-bottom: 1em;
}
.content ol > li {
    list-style-type: decimal;
}
.content ul,
.content ol {
    margin-left: 20px;
}
.content ul > li {
    position: relative;
    list-style-type: disc;
}
.content li > :first-child {
    padding-top: 0;
}
.content strong,
.content b {
    font-weight: bold;
}
.content i,
.content em {
    font-style: italic;
}
.content code {
    font-family: Menlo, monospace;
    background: #fb7a7a;
    padding: 1px 3px;
    font-size: 0.95em;
}
.content pre > code {
    display: block;
    background: transparent;
    font-size: 1em;
    letter-spacing: -1px;
}
/*.content blockquote :first-child {*/
    /*padding-top: 0;*/
/*}*/
/*.content blockquote :last-child {*/
    /*padding-bottom: 0;*/
/*}*/
.content table {
    margin-top: 10px;
    margin-bottom: 10px;
    padding: 0;
    border-collapse: collapse;
    clear: both;
}
.content table tr {
    border-top: 1px solid #ccc;
    background-color: #fff;
    margin: 0;
    padding: 0;
}
.content table tr :nth-child(2n) {
    background-color: #f8f8f8;
}
.content table tr th {
    text-align: auto;
    font-weight: bold;
    border: 1px solid #ccc;
    margin: 0;
    padding: 6px 13px;
}
.content table tr td {
    text-align: auto;
    border: 1px solid #ccc;
    margin: 0;
    padding: 6px 13px;
}
.content table tr th :first-child,
.content table tr td :first-child {
    margin-top: 0;
}
.content table tr th :last-child,
.content table tr td :last-child {
    margin-bottom: 0;
}
/* ----------------------------------------------------------------------------
 * Content
 */
.content-root {
    min-height: 90%;
    position: relative;
}
.content {
    padding-top: 30px;
    padding-bottom: 40px;
    padding-left: 40px;
    padding-right: 40px;
    zoom: 1;
    max-width: 800px;
}
.content:before,
.content:after {
    content: "";
    display: table;
}
.content:after {
    clear: both;
}
.content blockquote {
    color: #ffffff;
    text-shadow: 0 1px 0 rgba(255,255,255,0.5);
    background: #e06262;
    padding-left: 1rem;
}

.content h1 {
    font-weight: 300;
    font-size: 3rem;
    letter-spacing: 1px;
}

.content h2 {
    font-size: 2rem;
    font-weight: 300;
    color: #e8e8e8;
}
.content h3 {
    font-size: 1.5rem;
    font-weight: 300;
    color: #e8e8e8;
}

.content h4 {
    font-size: 1.2rem;
    font-weight: 400;
}


/*@media (max-width: 768px) {*/
    /*.content h1,*/
    /*.content h2,*/
    /*.content h1:before,*/
    /*.content h2:before {*/
        /*background: #dfe2e7;*/
        /*left: -40px;*/
        /*top: -20px;*/
        /*width: 120%;*/
    /*}*/
/*}*/
/*.content h4,*/
/*.content h5,*/
/*.content .small-heading {*/
    /*border-bottom: solid 1px rgba(0,0,0,0.07);*/
    /*color: #9090aa;*/
    /*padding-top: 30px;*/
    /*padding-bottom: 10px;*/
/*}*/
/*.content h1:first-child {*/
    /*padding-top: 0;*/
/*}*/
/*.content h1:first-child,*/
/*.content h1:first-child a,*/
/*.content h1:first-child a:visited {*/
    /*color: #505050;*/
/*}*/
/*.content h1:first-child:before {*/
    /*display: none;*/
/*}*/
@media (max-width: 768px) {
    .content h4,
    .content h5,
    .content .small-heading {
        padding-top: 20px;
    }
}
@media (max-width: 480px) {
    .content {
        padding: 20px;
        padding-top: 40px;
    }
    .content h4,
    .content h5,
    .content .small-heading {
        padding-top: 10px;
    }
}

.container, .content, .content-inner {
    min-height: 100%;
}

.editor-textarea {
    width: 100%;
    height: 80%;
    font-family: Consolas, Monaco, 'Andale Mono', monospace;
    color: rgb(248, 248, 242);
    font-weight: normal;
    padding: 10px;
    margin: 0px;
    width: 700px;
    height: 604px;
    background: rgb(39, 40, 34);
    outline: 0;
}

/* ------------------------------------------------------------------------------------------------------------------
LOGIN PAGE
------------------------------------------------------------------------------------------------------------------ */
.login-page {
    background: #33332F;
}

.login-page form {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 260px;
    min-height: 200px;
    margin: -150px 0 0 -150px;
    text-align: center;
    /*background: #ef8d31;*/
    background: rgb(177, 199, 57);
    border-bottom: 8px solid #272727;
    padding: 20px;
}

.login-page .form-group {
    margin-bottom: 10px;
}

.login-page .form-group.checkbox {
    text-align: left;
}

.login-page input[type=text], .login-page input[type=password] {
    border: 0;
    width: 100%;
    padding: 10px 15px;
    box-sizing: border-box;
}

.login-page button {
    width: 100%;
    border: 0;
    background: #353535;
    color: #fff;
    padding: 10px;
}

.code-action-download {
    background: rgba(255, 255, 255, 0.7);
    top: -19px;
    position: relative;
    color: #d48787;
    text-transform: uppercase;
    font-size: 10px;
    height: 25px;
    display: inline-block;
    padding: 6px 6px;
}
.code-action-download:hover {
    /*background: rgba(255, 255, 255, 0.5);*/
    color: #000;
}

/**
 * prism.js Coy theme for JavaScript, CoffeeScript, CSS and HTML
 * Based on https://github.com/tshedor/workshop-wp-theme (Example: http://workshop.kansan.com/category/sessions/basics or http://workshop.timshedor.com/category/sessions/basics);
 * @author Tim Shedor
 */
code[class*="language-"],
pre[class*="language-"] {
    color: black;
    font-family: Consolas, Monaco, 'Andale Mono', monospace;
    direction: ltr;
    text-align: left;
    white-space: pre;
    word-spacing: normal;
    word-break: normal;
    line-height: 1.5;

    -moz-tab-size: 4;
    -o-tab-size: 4;
    tab-size: 4;

    -webkit-hyphens: none;
    -moz-hyphens: none;
    -ms-hyphens: none;
    hyphens: none;
}

/* Code blocks */
pre[class*="language-"] {
    position: relative;
    margin: .5em 0;
    overflow: visible;
    max-height: 30em;
}

code[class*="language"] {
    max-height: inherit;
    height: 100%;
    padding: 1em;
    display: block;
    overflow: auto;
}

/* Margin bottom to accomodate shadow */
:not(pre) > code[class*="language-"],
pre[class*="language-"] {
    background-color: #fdfdfd;
    -webkit-box-sizing: border-box;
    -moz-box-sizing: border-box;
    box-sizing: border-box;
    margin-bottom: 1em;
}

/* Inline code */
:not(pre) > code[class*="language-"] {
    position: relative;
    padding: .2em;
    -webkit-border-radius: 0.3em;
    -moz-border-radius: 0.3em;
    -ms-border-radius: 0.3em;
    -o-border-radius: 0.3em;
    border-radius: 0.3em;
    color: #c92c2c;
    border: 1px solid rgba(0, 0, 0, 0.1);
}

pre[class*="language-"]:before,
pre[class*="language-"]:after {
    content: '';
    z-index: -2;
    display: block;
    position: absolute;
    bottom: 0.75em;
    left: 0.18em;
    width: 40%;
    height: 20%;
}

:not(pre) > code[class*="language-"]:after,
pre[class*="language-"]:after {
    right: 0.75em;
    left: auto;
}

.token.comment,
.token.block-comment,
.token.prolog,
.token.doctype,
.token.cdata {
    color: #7D8B99;
}

.token.punctuation {
    color: #5F6364;
}

.token.property,
.token.tag,
.token.boolean,
.token.number,
.token.function-name,
.token.constant,
.token.symbol,
.token.deleted {
    color: #c92c2c;
}

.token.selector,
.token.attr-name,
.token.string,
.token.char,
.token.function,
.token.builtin,
.token.inserted {
    color: #2f9c0a;
}

.token.operator,
.token.entity,
.token.url,
.token.variable {
    color: #a67f59;
    background: rgba(255, 255, 255, 0.5);
}

.token.atrule,
.token.attr-value,
.token.keyword,
.token.class-name {
    color: #1990b8;
}

.token.regex,
.token.important {
    color: #e90;
}

.language-css .token.string,
.style .token.string {
    color: #a67f59;
    background: rgba(255, 255, 255, 0.5);
}

.token.important {
    font-weight: normal;
}

.token.bold {
    font-weight: bold;
}
.token.italic {
    font-style: italic;
}

.token.entity {
    cursor: help;
}

.namespace {
    opacity: .7;
}

@media screen and (max-width: 767px) {
    pre[class*="language-"]:before,
    pre[class*="language-"]:after {
        bottom: 14px;
        -webkit-box-shadow: none;
        -moz-box-shadow: none;
        box-shadow: none;
    }

}

/* Plugin styles */
.token.tab:not(:empty):before,
.token.cr:before,
.token.lf:before {
    color: #e0d7d1;
}

/* Plugin styles: Line Numbers */
pre[class*="language-"].line-numbers {
    padding-left: 0;
}

pre[class*="language-"].line-numbers code {
    padding-left: 3.8em;
}

pre[class*="language-"].line-numbers .line-numbers-rows {
    left: 0;
}


    <?php
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}


function resource_37985b64af1bdcd122088bb166b9a3e9() {
    ob_start(); ?>
    /**
 * Created by ofca on 2015-02-21.
 */
/*! jQuery v2.1.3 | (c) 2005, 2014 jQuery Foundation, Inc. | jquery.org/license */
!function(a,b){"object"==typeof module&&"object"==typeof module.exports?module.exports=a.document?b(a,!0):function(a){if(!a.document)throw new Error("jQuery requires a window with a document");return b(a)}:b(a)}("undefined"!=typeof window?window:this,function(a,b){var c=[],d=c.slice,e=c.concat,f=c.push,g=c.indexOf,h={},i=h.toString,j=h.hasOwnProperty,k={},l=a.document,m="2.1.3",n=function(a,b){return new n.fn.init(a,b)},o=/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g,p=/^-ms-/,q=/-([\da-z])/gi,r=function(a,b){return b.toUpperCase()};n.fn=n.prototype={jquery:m,constructor:n,selector:"",length:0,toArray:function(){return d.call(this)},get:function(a){return null!=a?0>a?this[a+this.length]:this[a]:d.call(this)},pushStack:function(a){var b=n.merge(this.constructor(),a);return b.prevObject=this,b.context=this.context,b},each:function(a,b){return n.each(this,a,b)},map:function(a){return this.pushStack(n.map(this,function(b,c){return a.call(b,c,b)}))},slice:function(){return this.pushStack(d.apply(this,arguments))},first:function(){return this.eq(0)},last:function(){return this.eq(-1)},eq:function(a){var b=this.length,c=+a+(0>a?b:0);return this.pushStack(c>=0&&b>c?[this[c]]:[])},end:function(){return this.prevObject||this.constructor(null)},push:f,sort:c.sort,splice:c.splice},n.extend=n.fn.extend=function(){var a,b,c,d,e,f,g=arguments[0]||{},h=1,i=arguments.length,j=!1;for("boolean"==typeof g&&(j=g,g=arguments[h]||{},h++),"object"==typeof g||n.isFunction(g)||(g={}),h===i&&(g=this,h--);i>h;h++)if(null!=(a=arguments[h]))for(b in a)c=g[b],d=a[b],g!==d&&(j&&d&&(n.isPlainObject(d)||(e=n.isArray(d)))?(e?(e=!1,f=c&&n.isArray(c)?c:[]):f=c&&n.isPlainObject(c)?c:{},g[b]=n.extend(j,f,d)):void 0!==d&&(g[b]=d));return g},n.extend({expando:"jQuery"+(m+Math.random()).replace(/\D/g,""),isReady:!0,error:function(a){throw new Error(a)},noop:function(){},isFunction:function(a){return"function"===n.type(a)},isArray:Array.isArray,isWindow:function(a){return null!=a&&a===a.window},isNumeric:function(a){return!n.isArray(a)&&a-parseFloat(a)+1>=0},isPlainObject:function(a){return"object"!==n.type(a)||a.nodeType||n.isWindow(a)?!1:a.constructor&&!j.call(a.constructor.prototype,"isPrototypeOf")?!1:!0},isEmptyObject:function(a){var b;for(b in a)return!1;return!0},type:function(a){return null==a?a+"":"object"==typeof a||"function"==typeof a?h[i.call(a)]||"object":typeof a},globalEval:function(a){var b,c=eval;a=n.trim(a),a&&(1===a.indexOf("use strict")?(b=l.createElement("script"),b.text=a,l.head.appendChild(b).parentNode.removeChild(b)):c(a))},camelCase:function(a){return a.replace(p,"ms-").replace(q,r)},nodeName:function(a,b){return a.nodeName&&a.nodeName.toLowerCase()===b.toLowerCase()},each:function(a,b,c){var d,e=0,f=a.length,g=s(a);if(c){if(g){for(;f>e;e++)if(d=b.apply(a[e],c),d===!1)break}else for(e in a)if(d=b.apply(a[e],c),d===!1)break}else if(g){for(;f>e;e++)if(d=b.call(a[e],e,a[e]),d===!1)break}else for(e in a)if(d=b.call(a[e],e,a[e]),d===!1)break;return a},trim:function(a){return null==a?"":(a+"").replace(o,"")},makeArray:function(a,b){var c=b||[];return null!=a&&(s(Object(a))?n.merge(c,"string"==typeof a?[a]:a):f.call(c,a)),c},inArray:function(a,b,c){return null==b?-1:g.call(b,a,c)},merge:function(a,b){for(var c=+b.length,d=0,e=a.length;c>d;d++)a[e++]=b[d];return a.length=e,a},grep:function(a,b,c){for(var d,e=[],f=0,g=a.length,h=!c;g>f;f++)d=!b(a[f],f),d!==h&&e.push(a[f]);return e},map:function(a,b,c){var d,f=0,g=a.length,h=s(a),i=[];if(h)for(;g>f;f++)d=b(a[f],f,c),null!=d&&i.push(d);else for(f in a)d=b(a[f],f,c),null!=d&&i.push(d);return e.apply([],i)},guid:1,proxy:function(a,b){var c,e,f;return"string"==typeof b&&(c=a[b],b=a,a=c),n.isFunction(a)?(e=d.call(arguments,2),f=function(){return a.apply(b||this,e.concat(d.call(arguments)))},f.guid=a.guid=a.guid||n.guid++,f):void 0},now:Date.now,support:k}),n.each("Boolean Number String Function Array Date RegExp Object Error".split(" "),function(a,b){h["[object "+b+"]"]=b.toLowerCase()});function s(a){var b=a.length,c=n.type(a);return"function"===c||n.isWindow(a)?!1:1===a.nodeType&&b?!0:"array"===c||0===b||"number"==typeof b&&b>0&&b-1 in a}var t=function(a){var b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u="sizzle"+1*new Date,v=a.document,w=0,x=0,y=hb(),z=hb(),A=hb(),B=function(a,b){return a===b&&(l=!0),0},C=1<<31,D={}.hasOwnProperty,E=[],F=E.pop,G=E.push,H=E.push,I=E.slice,J=function(a,b){for(var c=0,d=a.length;d>c;c++)if(a[c]===b)return c;return-1},K="checked|selected|async|autofocus|autoplay|controls|defer|disabled|hidden|ismap|loop|multiple|open|readonly|required|scoped",L="[\\x20\\t\\r\\n\\f]",M="(?:\\\\.|[\\w-]|[^\\x00-\\xa0])+",N=M.replace("w","w#"),O="\\["+L+"*("+M+")(?:"+L+"*([*^$|!~]?=)"+L+"*(?:'((?:\\\\.|[^\\\\'])*)'|\"((?:\\\\.|[^\\\\\"])*)\"|("+N+"))|)"+L+"*\\]",P=":("+M+")(?:\\((('((?:\\\\.|[^\\\\'])*)'|\"((?:\\\\.|[^\\\\\"])*)\")|((?:\\\\.|[^\\\\()[\\]]|"+O+")*)|.*)\\)|)",Q=new RegExp(L+"+","g"),R=new RegExp("^"+L+"+|((?:^|[^\\\\])(?:\\\\.)*)"+L+"+$","g"),S=new RegExp("^"+L+"*,"+L+"*"),T=new RegExp("^"+L+"*([>+~]|"+L+")"+L+"*"),U=new RegExp("="+L+"*([^\\]'\"]*?)"+L+"*\\]","g"),V=new RegExp(P),W=new RegExp("^"+N+"$"),X={ID:new RegExp("^#("+M+")"),CLASS:new RegExp("^\\.("+M+")"),TAG:new RegExp("^("+M.replace("w","w*")+")"),ATTR:new RegExp("^"+O),PSEUDO:new RegExp("^"+P),CHILD:new RegExp("^:(only|first|last|nth|nth-last)-(child|of-type)(?:\\("+L+"*(even|odd|(([+-]|)(\\d*)n|)"+L+"*(?:([+-]|)"+L+"*(\\d+)|))"+L+"*\\)|)","i"),bool:new RegExp("^(?:"+K+")$","i"),needsContext:new RegExp("^"+L+"*[>+~]|:(even|odd|eq|gt|lt|nth|first|last)(?:\\("+L+"*((?:-\\d)?\\d*)"+L+"*\\)|)(?=[^-]|$)","i")},Y=/^(?:input|select|textarea|button)$/i,Z=/^h\d$/i,$=/^[^{]+\{\s*\[native \w/,_=/^(?:#([\w-]+)|(\w+)|\.([\w-]+))$/,ab=/[+~]/,bb=/'|\\/g,cb=new RegExp("\\\\([\\da-f]{1,6}"+L+"?|("+L+")|.)","ig"),db=function(a,b,c){var d="0x"+b-65536;return d!==d||c?b:0>d?String.fromCharCode(d+65536):String.fromCharCode(d>>10|55296,1023&d|56320)},eb=function(){m()};try{H.apply(E=I.call(v.childNodes),v.childNodes),E[v.childNodes.length].nodeType}catch(fb){H={apply:E.length?function(a,b){G.apply(a,I.call(b))}:function(a,b){var c=a.length,d=0;while(a[c++]=b[d++]);a.length=c-1}}}function gb(a,b,d,e){var f,h,j,k,l,o,r,s,w,x;if((b?b.ownerDocument||b:v)!==n&&m(b),b=b||n,d=d||[],k=b.nodeType,"string"!=typeof a||!a||1!==k&&9!==k&&11!==k)return d;if(!e&&p){if(11!==k&&(f=_.exec(a)))if(j=f[1]){if(9===k){if(h=b.getElementById(j),!h||!h.parentNode)return d;if(h.id===j)return d.push(h),d}else if(b.ownerDocument&&(h=b.ownerDocument.getElementById(j))&&t(b,h)&&h.id===j)return d.push(h),d}else{if(f[2])return H.apply(d,b.getElementsByTagName(a)),d;if((j=f[3])&&c.getElementsByClassName)return H.apply(d,b.getElementsByClassName(j)),d}if(c.qsa&&(!q||!q.test(a))){if(s=r=u,w=b,x=1!==k&&a,1===k&&"object"!==b.nodeName.toLowerCase()){o=g(a),(r=b.getAttribute("id"))?s=r.replace(bb,"\\$&"):b.setAttribute("id",s),s="[id='"+s+"'] ",l=o.length;while(l--)o[l]=s+rb(o[l]);w=ab.test(a)&&pb(b.parentNode)||b,x=o.join(",")}if(x)try{return H.apply(d,w.querySelectorAll(x)),d}catch(y){}finally{r||b.removeAttribute("id")}}}return i(a.replace(R,"$1"),b,d,e)}function hb(){var a=[];function b(c,e){return a.push(c+" ")>d.cacheLength&&delete b[a.shift()],b[c+" "]=e}return b}function ib(a){return a[u]=!0,a}function jb(a){var b=n.createElement("div");try{return!!a(b)}catch(c){return!1}finally{b.parentNode&&b.parentNode.removeChild(b),b=null}}function kb(a,b){var c=a.split("|"),e=a.length;while(e--)d.attrHandle[c[e]]=b}function lb(a,b){var c=b&&a,d=c&&1===a.nodeType&&1===b.nodeType&&(~b.sourceIndex||C)-(~a.sourceIndex||C);if(d)return d;if(c)while(c=c.nextSibling)if(c===b)return-1;return a?1:-1}function mb(a){return function(b){var c=b.nodeName.toLowerCase();return"input"===c&&b.type===a}}function nb(a){return function(b){var c=b.nodeName.toLowerCase();return("input"===c||"button"===c)&&b.type===a}}function ob(a){return ib(function(b){return b=+b,ib(function(c,d){var e,f=a([],c.length,b),g=f.length;while(g--)c[e=f[g]]&&(c[e]=!(d[e]=c[e]))})})}function pb(a){return a&&"undefined"!=typeof a.getElementsByTagName&&a}c=gb.support={},f=gb.isXML=function(a){var b=a&&(a.ownerDocument||a).documentElement;return b?"HTML"!==b.nodeName:!1},m=gb.setDocument=function(a){var b,e,g=a?a.ownerDocument||a:v;return g!==n&&9===g.nodeType&&g.documentElement?(n=g,o=g.documentElement,e=g.defaultView,e&&e!==e.top&&(e.addEventListener?e.addEventListener("unload",eb,!1):e.attachEvent&&e.attachEvent("onunload",eb)),p=!f(g),c.attributes=jb(function(a){return a.className="i",!a.getAttribute("className")}),c.getElementsByTagName=jb(function(a){return a.appendChild(g.createComment("")),!a.getElementsByTagName("*").length}),c.getElementsByClassName=$.test(g.getElementsByClassName),c.getById=jb(function(a){return o.appendChild(a).id=u,!g.getElementsByName||!g.getElementsByName(u).length}),c.getById?(d.find.ID=function(a,b){if("undefined"!=typeof b.getElementById&&p){var c=b.getElementById(a);return c&&c.parentNode?[c]:[]}},d.filter.ID=function(a){var b=a.replace(cb,db);return function(a){return a.getAttribute("id")===b}}):(delete d.find.ID,d.filter.ID=function(a){var b=a.replace(cb,db);return function(a){var c="undefined"!=typeof a.getAttributeNode&&a.getAttributeNode("id");return c&&c.value===b}}),d.find.TAG=c.getElementsByTagName?function(a,b){return"undefined"!=typeof b.getElementsByTagName?b.getElementsByTagName(a):c.qsa?b.querySelectorAll(a):void 0}:function(a,b){var c,d=[],e=0,f=b.getElementsByTagName(a);if("*"===a){while(c=f[e++])1===c.nodeType&&d.push(c);return d}return f},d.find.CLASS=c.getElementsByClassName&&function(a,b){return p?b.getElementsByClassName(a):void 0},r=[],q=[],(c.qsa=$.test(g.querySelectorAll))&&(jb(function(a){o.appendChild(a).innerHTML="<a id='"+u+"'></a><select id='"+u+"-\f]' msallowcapture=''><option selected=''></option></select>",a.querySelectorAll("[msallowcapture^='']").length&&q.push("[*^$]="+L+"*(?:''|\"\")"),a.querySelectorAll("[selected]").length||q.push("\\["+L+"*(?:value|"+K+")"),a.querySelectorAll("[id~="+u+"-]").length||q.push("~="),a.querySelectorAll(":checked").length||q.push(":checked"),a.querySelectorAll("a#"+u+"+*").length||q.push(".#.+[+~]")}),jb(function(a){var b=g.createElement("input");b.setAttribute("type","hidden"),a.appendChild(b).setAttribute("name","D"),a.querySelectorAll("[name=d]").length&&q.push("name"+L+"*[*^$|!~]?="),a.querySelectorAll(":enabled").length||q.push(":enabled",":disabled"),a.querySelectorAll("*,:x"),q.push(",.*:")})),(c.matchesSelector=$.test(s=o.matches||o.webkitMatchesSelector||o.mozMatchesSelector||o.oMatchesSelector||o.msMatchesSelector))&&jb(function(a){c.disconnectedMatch=s.call(a,"div"),s.call(a,"[s!='']:x"),r.push("!=",P)}),q=q.length&&new RegExp(q.join("|")),r=r.length&&new RegExp(r.join("|")),b=$.test(o.compareDocumentPosition),t=b||$.test(o.contains)?function(a,b){var c=9===a.nodeType?a.documentElement:a,d=b&&b.parentNode;return a===d||!(!d||1!==d.nodeType||!(c.contains?c.contains(d):a.compareDocumentPosition&&16&a.compareDocumentPosition(d)))}:function(a,b){if(b)while(b=b.parentNode)if(b===a)return!0;return!1},B=b?function(a,b){if(a===b)return l=!0,0;var d=!a.compareDocumentPosition-!b.compareDocumentPosition;return d?d:(d=(a.ownerDocument||a)===(b.ownerDocument||b)?a.compareDocumentPosition(b):1,1&d||!c.sortDetached&&b.compareDocumentPosition(a)===d?a===g||a.ownerDocument===v&&t(v,a)?-1:b===g||b.ownerDocument===v&&t(v,b)?1:k?J(k,a)-J(k,b):0:4&d?-1:1)}:function(a,b){if(a===b)return l=!0,0;var c,d=0,e=a.parentNode,f=b.parentNode,h=[a],i=[b];if(!e||!f)return a===g?-1:b===g?1:e?-1:f?1:k?J(k,a)-J(k,b):0;if(e===f)return lb(a,b);c=a;while(c=c.parentNode)h.unshift(c);c=b;while(c=c.parentNode)i.unshift(c);while(h[d]===i[d])d++;return d?lb(h[d],i[d]):h[d]===v?-1:i[d]===v?1:0},g):n},gb.matches=function(a,b){return gb(a,null,null,b)},gb.matchesSelector=function(a,b){if((a.ownerDocument||a)!==n&&m(a),b=b.replace(U,"='$1']"),!(!c.matchesSelector||!p||r&&r.test(b)||q&&q.test(b)))try{var d=s.call(a,b);if(d||c.disconnectedMatch||a.document&&11!==a.document.nodeType)return d}catch(e){}return gb(b,n,null,[a]).length>0},gb.contains=function(a,b){return(a.ownerDocument||a)!==n&&m(a),t(a,b)},gb.attr=function(a,b){(a.ownerDocument||a)!==n&&m(a);var e=d.attrHandle[b.toLowerCase()],f=e&&D.call(d.attrHandle,b.toLowerCase())?e(a,b,!p):void 0;return void 0!==f?f:c.attributes||!p?a.getAttribute(b):(f=a.getAttributeNode(b))&&f.specified?f.value:null},gb.error=function(a){throw new Error("Syntax error, unrecognized expression: "+a)},gb.uniqueSort=function(a){var b,d=[],e=0,f=0;if(l=!c.detectDuplicates,k=!c.sortStable&&a.slice(0),a.sort(B),l){while(b=a[f++])b===a[f]&&(e=d.push(f));while(e--)a.splice(d[e],1)}return k=null,a},e=gb.getText=function(a){var b,c="",d=0,f=a.nodeType;if(f){if(1===f||9===f||11===f){if("string"==typeof a.textContent)return a.textContent;for(a=a.firstChild;a;a=a.nextSibling)c+=e(a)}else if(3===f||4===f)return a.nodeValue}else while(b=a[d++])c+=e(b);return c},d=gb.selectors={cacheLength:50,createPseudo:ib,match:X,attrHandle:{},find:{},relative:{">":{dir:"parentNode",first:!0}," ":{dir:"parentNode"},"+":{dir:"previousSibling",first:!0},"~":{dir:"previousSibling"}},preFilter:{ATTR:function(a){return a[1]=a[1].replace(cb,db),a[3]=(a[3]||a[4]||a[5]||"").replace(cb,db),"~="===a[2]&&(a[3]=" "+a[3]+" "),a.slice(0,4)},CHILD:function(a){return a[1]=a[1].toLowerCase(),"nth"===a[1].slice(0,3)?(a[3]||gb.error(a[0]),a[4]=+(a[4]?a[5]+(a[6]||1):2*("even"===a[3]||"odd"===a[3])),a[5]=+(a[7]+a[8]||"odd"===a[3])):a[3]&&gb.error(a[0]),a},PSEUDO:function(a){var b,c=!a[6]&&a[2];return X.CHILD.test(a[0])?null:(a[3]?a[2]=a[4]||a[5]||"":c&&V.test(c)&&(b=g(c,!0))&&(b=c.indexOf(")",c.length-b)-c.length)&&(a[0]=a[0].slice(0,b),a[2]=c.slice(0,b)),a.slice(0,3))}},filter:{TAG:function(a){var b=a.replace(cb,db).toLowerCase();return"*"===a?function(){return!0}:function(a){return a.nodeName&&a.nodeName.toLowerCase()===b}},CLASS:function(a){var b=y[a+" "];return b||(b=new RegExp("(^|"+L+")"+a+"("+L+"|$)"))&&y(a,function(a){return b.test("string"==typeof a.className&&a.className||"undefined"!=typeof a.getAttribute&&a.getAttribute("class")||"")})},ATTR:function(a,b,c){return function(d){var e=gb.attr(d,a);return null==e?"!="===b:b?(e+="","="===b?e===c:"!="===b?e!==c:"^="===b?c&&0===e.indexOf(c):"*="===b?c&&e.indexOf(c)>-1:"$="===b?c&&e.slice(-c.length)===c:"~="===b?(" "+e.replace(Q," ")+" ").indexOf(c)>-1:"|="===b?e===c||e.slice(0,c.length+1)===c+"-":!1):!0}},CHILD:function(a,b,c,d,e){var f="nth"!==a.slice(0,3),g="last"!==a.slice(-4),h="of-type"===b;return 1===d&&0===e?function(a){return!!a.parentNode}:function(b,c,i){var j,k,l,m,n,o,p=f!==g?"nextSibling":"previousSibling",q=b.parentNode,r=h&&b.nodeName.toLowerCase(),s=!i&&!h;if(q){if(f){while(p){l=b;while(l=l[p])if(h?l.nodeName.toLowerCase()===r:1===l.nodeType)return!1;o=p="only"===a&&!o&&"nextSibling"}return!0}if(o=[g?q.firstChild:q.lastChild],g&&s){k=q[u]||(q[u]={}),j=k[a]||[],n=j[0]===w&&j[1],m=j[0]===w&&j[2],l=n&&q.childNodes[n];while(l=++n&&l&&l[p]||(m=n=0)||o.pop())if(1===l.nodeType&&++m&&l===b){k[a]=[w,n,m];break}}else if(s&&(j=(b[u]||(b[u]={}))[a])&&j[0]===w)m=j[1];else while(l=++n&&l&&l[p]||(m=n=0)||o.pop())if((h?l.nodeName.toLowerCase()===r:1===l.nodeType)&&++m&&(s&&((l[u]||(l[u]={}))[a]=[w,m]),l===b))break;return m-=e,m===d||m%d===0&&m/d>=0}}},PSEUDO:function(a,b){var c,e=d.pseudos[a]||d.setFilters[a.toLowerCase()]||gb.error("unsupported pseudo: "+a);return e[u]?e(b):e.length>1?(c=[a,a,"",b],d.setFilters.hasOwnProperty(a.toLowerCase())?ib(function(a,c){var d,f=e(a,b),g=f.length;while(g--)d=J(a,f[g]),a[d]=!(c[d]=f[g])}):function(a){return e(a,0,c)}):e}},pseudos:{not:ib(function(a){var b=[],c=[],d=h(a.replace(R,"$1"));return d[u]?ib(function(a,b,c,e){var f,g=d(a,null,e,[]),h=a.length;while(h--)(f=g[h])&&(a[h]=!(b[h]=f))}):function(a,e,f){return b[0]=a,d(b,null,f,c),b[0]=null,!c.pop()}}),has:ib(function(a){return function(b){return gb(a,b).length>0}}),contains:ib(function(a){return a=a.replace(cb,db),function(b){return(b.textContent||b.innerText||e(b)).indexOf(a)>-1}}),lang:ib(function(a){return W.test(a||"")||gb.error("unsupported lang: "+a),a=a.replace(cb,db).toLowerCase(),function(b){var c;do if(c=p?b.lang:b.getAttribute("xml:lang")||b.getAttribute("lang"))return c=c.toLowerCase(),c===a||0===c.indexOf(a+"-");while((b=b.parentNode)&&1===b.nodeType);return!1}}),target:function(b){var c=a.location&&a.location.hash;return c&&c.slice(1)===b.id},root:function(a){return a===o},focus:function(a){return a===n.activeElement&&(!n.hasFocus||n.hasFocus())&&!!(a.type||a.href||~a.tabIndex)},enabled:function(a){return a.disabled===!1},disabled:function(a){return a.disabled===!0},checked:function(a){var b=a.nodeName.toLowerCase();return"input"===b&&!!a.checked||"option"===b&&!!a.selected},selected:function(a){return a.parentNode&&a.parentNode.selectedIndex,a.selected===!0},empty:function(a){for(a=a.firstChild;a;a=a.nextSibling)if(a.nodeType<6)return!1;return!0},parent:function(a){return!d.pseudos.empty(a)},header:function(a){return Z.test(a.nodeName)},input:function(a){return Y.test(a.nodeName)},button:function(a){var b=a.nodeName.toLowerCase();return"input"===b&&"button"===a.type||"button"===b},text:function(a){var b;return"input"===a.nodeName.toLowerCase()&&"text"===a.type&&(null==(b=a.getAttribute("type"))||"text"===b.toLowerCase())},first:ob(function(){return[0]}),last:ob(function(a,b){return[b-1]}),eq:ob(function(a,b,c){return[0>c?c+b:c]}),even:ob(function(a,b){for(var c=0;b>c;c+=2)a.push(c);return a}),odd:ob(function(a,b){for(var c=1;b>c;c+=2)a.push(c);return a}),lt:ob(function(a,b,c){for(var d=0>c?c+b:c;--d>=0;)a.push(d);return a}),gt:ob(function(a,b,c){for(var d=0>c?c+b:c;++d<b;)a.push(d);return a})}},d.pseudos.nth=d.pseudos.eq;for(b in{radio:!0,checkbox:!0,file:!0,password:!0,image:!0})d.pseudos[b]=mb(b);for(b in{submit:!0,reset:!0})d.pseudos[b]=nb(b);function qb(){}qb.prototype=d.filters=d.pseudos,d.setFilters=new qb,g=gb.tokenize=function(a,b){var c,e,f,g,h,i,j,k=z[a+" "];if(k)return b?0:k.slice(0);h=a,i=[],j=d.preFilter;while(h){(!c||(e=S.exec(h)))&&(e&&(h=h.slice(e[0].length)||h),i.push(f=[])),c=!1,(e=T.exec(h))&&(c=e.shift(),f.push({value:c,type:e[0].replace(R," ")}),h=h.slice(c.length));for(g in d.filter)!(e=X[g].exec(h))||j[g]&&!(e=j[g](e))||(c=e.shift(),f.push({value:c,type:g,matches:e}),h=h.slice(c.length));if(!c)break}return b?h.length:h?gb.error(a):z(a,i).slice(0)};function rb(a){for(var b=0,c=a.length,d="";c>b;b++)d+=a[b].value;return d}function sb(a,b,c){var d=b.dir,e=c&&"parentNode"===d,f=x++;return b.first?function(b,c,f){while(b=b[d])if(1===b.nodeType||e)return a(b,c,f)}:function(b,c,g){var h,i,j=[w,f];if(g){while(b=b[d])if((1===b.nodeType||e)&&a(b,c,g))return!0}else while(b=b[d])if(1===b.nodeType||e){if(i=b[u]||(b[u]={}),(h=i[d])&&h[0]===w&&h[1]===f)return j[2]=h[2];if(i[d]=j,j[2]=a(b,c,g))return!0}}}function tb(a){return a.length>1?function(b,c,d){var e=a.length;while(e--)if(!a[e](b,c,d))return!1;return!0}:a[0]}function ub(a,b,c){for(var d=0,e=b.length;e>d;d++)gb(a,b[d],c);return c}function vb(a,b,c,d,e){for(var f,g=[],h=0,i=a.length,j=null!=b;i>h;h++)(f=a[h])&&(!c||c(f,d,e))&&(g.push(f),j&&b.push(h));return g}function wb(a,b,c,d,e,f){return d&&!d[u]&&(d=wb(d)),e&&!e[u]&&(e=wb(e,f)),ib(function(f,g,h,i){var j,k,l,m=[],n=[],o=g.length,p=f||ub(b||"*",h.nodeType?[h]:h,[]),q=!a||!f&&b?p:vb(p,m,a,h,i),r=c?e||(f?a:o||d)?[]:g:q;if(c&&c(q,r,h,i),d){j=vb(r,n),d(j,[],h,i),k=j.length;while(k--)(l=j[k])&&(r[n[k]]=!(q[n[k]]=l))}if(f){if(e||a){if(e){j=[],k=r.length;while(k--)(l=r[k])&&j.push(q[k]=l);e(null,r=[],j,i)}k=r.length;while(k--)(l=r[k])&&(j=e?J(f,l):m[k])>-1&&(f[j]=!(g[j]=l))}}else r=vb(r===g?r.splice(o,r.length):r),e?e(null,g,r,i):H.apply(g,r)})}function xb(a){for(var b,c,e,f=a.length,g=d.relative[a[0].type],h=g||d.relative[" "],i=g?1:0,k=sb(function(a){return a===b},h,!0),l=sb(function(a){return J(b,a)>-1},h,!0),m=[function(a,c,d){var e=!g&&(d||c!==j)||((b=c).nodeType?k(a,c,d):l(a,c,d));return b=null,e}];f>i;i++)if(c=d.relative[a[i].type])m=[sb(tb(m),c)];else{if(c=d.filter[a[i].type].apply(null,a[i].matches),c[u]){for(e=++i;f>e;e++)if(d.relative[a[e].type])break;return wb(i>1&&tb(m),i>1&&rb(a.slice(0,i-1).concat({value:" "===a[i-2].type?"*":""})).replace(R,"$1"),c,e>i&&xb(a.slice(i,e)),f>e&&xb(a=a.slice(e)),f>e&&rb(a))}m.push(c)}return tb(m)}function yb(a,b){var c=b.length>0,e=a.length>0,f=function(f,g,h,i,k){var l,m,o,p=0,q="0",r=f&&[],s=[],t=j,u=f||e&&d.find.TAG("*",k),v=w+=null==t?1:Math.random()||.1,x=u.length;for(k&&(j=g!==n&&g);q!==x&&null!=(l=u[q]);q++){if(e&&l){m=0;while(o=a[m++])if(o(l,g,h)){i.push(l);break}k&&(w=v)}c&&((l=!o&&l)&&p--,f&&r.push(l))}if(p+=q,c&&q!==p){m=0;while(o=b[m++])o(r,s,g,h);if(f){if(p>0)while(q--)r[q]||s[q]||(s[q]=F.call(i));s=vb(s)}H.apply(i,s),k&&!f&&s.length>0&&p+b.length>1&&gb.uniqueSort(i)}return k&&(w=v,j=t),r};return c?ib(f):f}return h=gb.compile=function(a,b){var c,d=[],e=[],f=A[a+" "];if(!f){b||(b=g(a)),c=b.length;while(c--)f=xb(b[c]),f[u]?d.push(f):e.push(f);f=A(a,yb(e,d)),f.selector=a}return f},i=gb.select=function(a,b,e,f){var i,j,k,l,m,n="function"==typeof a&&a,o=!f&&g(a=n.selector||a);if(e=e||[],1===o.length){if(j=o[0]=o[0].slice(0),j.length>2&&"ID"===(k=j[0]).type&&c.getById&&9===b.nodeType&&p&&d.relative[j[1].type]){if(b=(d.find.ID(k.matches[0].replace(cb,db),b)||[])[0],!b)return e;n&&(b=b.parentNode),a=a.slice(j.shift().value.length)}i=X.needsContext.test(a)?0:j.length;while(i--){if(k=j[i],d.relative[l=k.type])break;if((m=d.find[l])&&(f=m(k.matches[0].replace(cb,db),ab.test(j[0].type)&&pb(b.parentNode)||b))){if(j.splice(i,1),a=f.length&&rb(j),!a)return H.apply(e,f),e;break}}}return(n||h(a,o))(f,b,!p,e,ab.test(a)&&pb(b.parentNode)||b),e},c.sortStable=u.split("").sort(B).join("")===u,c.detectDuplicates=!!l,m(),c.sortDetached=jb(function(a){return 1&a.compareDocumentPosition(n.createElement("div"))}),jb(function(a){return a.innerHTML="<a href='#'></a>","#"===a.firstChild.getAttribute("href")})||kb("type|href|height|width",function(a,b,c){return c?void 0:a.getAttribute(b,"type"===b.toLowerCase()?1:2)}),c.attributes&&jb(function(a){return a.innerHTML="<input/>",a.firstChild.setAttribute("value",""),""===a.firstChild.getAttribute("value")})||kb("value",function(a,b,c){return c||"input"!==a.nodeName.toLowerCase()?void 0:a.defaultValue}),jb(function(a){return null==a.getAttribute("disabled")})||kb(K,function(a,b,c){var d;return c?void 0:a[b]===!0?b.toLowerCase():(d=a.getAttributeNode(b))&&d.specified?d.value:null}),gb}(a);n.find=t,n.expr=t.selectors,n.expr[":"]=n.expr.pseudos,n.unique=t.uniqueSort,n.text=t.getText,n.isXMLDoc=t.isXML,n.contains=t.contains;var u=n.expr.match.needsContext,v=/^<(\w+)\s*\/?>(?:<\/\1>|)$/,w=/^.[^:#\[\.,]*$/;function x(a,b,c){if(n.isFunction(b))return n.grep(a,function(a,d){return!!b.call(a,d,a)!==c});if(b.nodeType)return n.grep(a,function(a){return a===b!==c});if("string"==typeof b){if(w.test(b))return n.filter(b,a,c);b=n.filter(b,a)}return n.grep(a,function(a){return g.call(b,a)>=0!==c})}n.filter=function(a,b,c){var d=b[0];return c&&(a=":not("+a+")"),1===b.length&&1===d.nodeType?n.find.matchesSelector(d,a)?[d]:[]:n.find.matches(a,n.grep(b,function(a){return 1===a.nodeType}))},n.fn.extend({find:function(a){var b,c=this.length,d=[],e=this;if("string"!=typeof a)return this.pushStack(n(a).filter(function(){for(b=0;c>b;b++)if(n.contains(e[b],this))return!0}));for(b=0;c>b;b++)n.find(a,e[b],d);return d=this.pushStack(c>1?n.unique(d):d),d.selector=this.selector?this.selector+" "+a:a,d},filter:function(a){return this.pushStack(x(this,a||[],!1))},not:function(a){return this.pushStack(x(this,a||[],!0))},is:function(a){return!!x(this,"string"==typeof a&&u.test(a)?n(a):a||[],!1).length}});var y,z=/^(?:\s*(<[\w\W]+>)[^>]*|#([\w-]*))$/,A=n.fn.init=function(a,b){var c,d;if(!a)return this;if("string"==typeof a){if(c="<"===a[0]&&">"===a[a.length-1]&&a.length>=3?[null,a,null]:z.exec(a),!c||!c[1]&&b)return!b||b.jquery?(b||y).find(a):this.constructor(b).find(a);if(c[1]){if(b=b instanceof n?b[0]:b,n.merge(this,n.parseHTML(c[1],b&&b.nodeType?b.ownerDocument||b:l,!0)),v.test(c[1])&&n.isPlainObject(b))for(c in b)n.isFunction(this[c])?this[c](b[c]):this.attr(c,b[c]);return this}return d=l.getElementById(c[2]),d&&d.parentNode&&(this.length=1,this[0]=d),this.context=l,this.selector=a,this}return a.nodeType?(this.context=this[0]=a,this.length=1,this):n.isFunction(a)?"undefined"!=typeof y.ready?y.ready(a):a(n):(void 0!==a.selector&&(this.selector=a.selector,this.context=a.context),n.makeArray(a,this))};A.prototype=n.fn,y=n(l);var B=/^(?:parents|prev(?:Until|All))/,C={children:!0,contents:!0,next:!0,prev:!0};n.extend({dir:function(a,b,c){var d=[],e=void 0!==c;while((a=a[b])&&9!==a.nodeType)if(1===a.nodeType){if(e&&n(a).is(c))break;d.push(a)}return d},sibling:function(a,b){for(var c=[];a;a=a.nextSibling)1===a.nodeType&&a!==b&&c.push(a);return c}}),n.fn.extend({has:function(a){var b=n(a,this),c=b.length;return this.filter(function(){for(var a=0;c>a;a++)if(n.contains(this,b[a]))return!0})},closest:function(a,b){for(var c,d=0,e=this.length,f=[],g=u.test(a)||"string"!=typeof a?n(a,b||this.context):0;e>d;d++)for(c=this[d];c&&c!==b;c=c.parentNode)if(c.nodeType<11&&(g?g.index(c)>-1:1===c.nodeType&&n.find.matchesSelector(c,a))){f.push(c);break}return this.pushStack(f.length>1?n.unique(f):f)},index:function(a){return a?"string"==typeof a?g.call(n(a),this[0]):g.call(this,a.jquery?a[0]:a):this[0]&&this[0].parentNode?this.first().prevAll().length:-1},add:function(a,b){return this.pushStack(n.unique(n.merge(this.get(),n(a,b))))},addBack:function(a){return this.add(null==a?this.prevObject:this.prevObject.filter(a))}});function D(a,b){while((a=a[b])&&1!==a.nodeType);return a}n.each({parent:function(a){var b=a.parentNode;return b&&11!==b.nodeType?b:null},parents:function(a){return n.dir(a,"parentNode")},parentsUntil:function(a,b,c){return n.dir(a,"parentNode",c)},next:function(a){return D(a,"nextSibling")},prev:function(a){return D(a,"previousSibling")},nextAll:function(a){return n.dir(a,"nextSibling")},prevAll:function(a){return n.dir(a,"previousSibling")},nextUntil:function(a,b,c){return n.dir(a,"nextSibling",c)},prevUntil:function(a,b,c){return n.dir(a,"previousSibling",c)},siblings:function(a){return n.sibling((a.parentNode||{}).firstChild,a)},children:function(a){return n.sibling(a.firstChild)},contents:function(a){return a.contentDocument||n.merge([],a.childNodes)}},function(a,b){n.fn[a]=function(c,d){var e=n.map(this,b,c);return"Until"!==a.slice(-5)&&(d=c),d&&"string"==typeof d&&(e=n.filter(d,e)),this.length>1&&(C[a]||n.unique(e),B.test(a)&&e.reverse()),this.pushStack(e)}});var E=/\S+/g,F={};function G(a){var b=F[a]={};return n.each(a.match(E)||[],function(a,c){b[c]=!0}),b}n.Callbacks=function(a){a="string"==typeof a?F[a]||G(a):n.extend({},a);var b,c,d,e,f,g,h=[],i=!a.once&&[],j=function(l){for(b=a.memory&&l,c=!0,g=e||0,e=0,f=h.length,d=!0;h&&f>g;g++)if(h[g].apply(l[0],l[1])===!1&&a.stopOnFalse){b=!1;break}d=!1,h&&(i?i.length&&j(i.shift()):b?h=[]:k.disable())},k={add:function(){if(h){var c=h.length;!function g(b){n.each(b,function(b,c){var d=n.type(c);"function"===d?a.unique&&k.has(c)||h.push(c):c&&c.length&&"string"!==d&&g(c)})}(arguments),d?f=h.length:b&&(e=c,j(b))}return this},remove:function(){return h&&n.each(arguments,function(a,b){var c;while((c=n.inArray(b,h,c))>-1)h.splice(c,1),d&&(f>=c&&f--,g>=c&&g--)}),this},has:function(a){return a?n.inArray(a,h)>-1:!(!h||!h.length)},empty:function(){return h=[],f=0,this},disable:function(){return h=i=b=void 0,this},disabled:function(){return!h},lock:function(){return i=void 0,b||k.disable(),this},locked:function(){return!i},fireWith:function(a,b){return!h||c&&!i||(b=b||[],b=[a,b.slice?b.slice():b],d?i.push(b):j(b)),this},fire:function(){return k.fireWith(this,arguments),this},fired:function(){return!!c}};return k},n.extend({Deferred:function(a){var b=[["resolve","done",n.Callbacks("once memory"),"resolved"],["reject","fail",n.Callbacks("once memory"),"rejected"],["notify","progress",n.Callbacks("memory")]],c="pending",d={state:function(){return c},always:function(){return e.done(arguments).fail(arguments),this},then:function(){var a=arguments;return n.Deferred(function(c){n.each(b,function(b,f){var g=n.isFunction(a[b])&&a[b];e[f[1]](function(){var a=g&&g.apply(this,arguments);a&&n.isFunction(a.promise)?a.promise().done(c.resolve).fail(c.reject).progress(c.notify):c[f[0]+"With"](this===d?c.promise():this,g?[a]:arguments)})}),a=null}).promise()},promise:function(a){return null!=a?n.extend(a,d):d}},e={};return d.pipe=d.then,n.each(b,function(a,f){var g=f[2],h=f[3];d[f[1]]=g.add,h&&g.add(function(){c=h},b[1^a][2].disable,b[2][2].lock),e[f[0]]=function(){return e[f[0]+"With"](this===e?d:this,arguments),this},e[f[0]+"With"]=g.fireWith}),d.promise(e),a&&a.call(e,e),e},when:function(a){var b=0,c=d.call(arguments),e=c.length,f=1!==e||a&&n.isFunction(a.promise)?e:0,g=1===f?a:n.Deferred(),h=function(a,b,c){return function(e){b[a]=this,c[a]=arguments.length>1?d.call(arguments):e,c===i?g.notifyWith(b,c):--f||g.resolveWith(b,c)}},i,j,k;if(e>1)for(i=new Array(e),j=new Array(e),k=new Array(e);e>b;b++)c[b]&&n.isFunction(c[b].promise)?c[b].promise().done(h(b,k,c)).fail(g.reject).progress(h(b,j,i)):--f;return f||g.resolveWith(k,c),g.promise()}});var H;n.fn.ready=function(a){return n.ready.promise().done(a),this},n.extend({isReady:!1,readyWait:1,holdReady:function(a){a?n.readyWait++:n.ready(!0)},ready:function(a){(a===!0?--n.readyWait:n.isReady)||(n.isReady=!0,a!==!0&&--n.readyWait>0||(H.resolveWith(l,[n]),n.fn.triggerHandler&&(n(l).triggerHandler("ready"),n(l).off("ready"))))}});function I(){l.removeEventListener("DOMContentLoaded",I,!1),a.removeEventListener("load",I,!1),n.ready()}n.ready.promise=function(b){return H||(H=n.Deferred(),"complete"===l.readyState?setTimeout(n.ready):(l.addEventListener("DOMContentLoaded",I,!1),a.addEventListener("load",I,!1))),H.promise(b)},n.ready.promise();var J=n.access=function(a,b,c,d,e,f,g){var h=0,i=a.length,j=null==c;if("object"===n.type(c)){e=!0;for(h in c)n.access(a,b,h,c[h],!0,f,g)}else if(void 0!==d&&(e=!0,n.isFunction(d)||(g=!0),j&&(g?(b.call(a,d),b=null):(j=b,b=function(a,b,c){return j.call(n(a),c)})),b))for(;i>h;h++)b(a[h],c,g?d:d.call(a[h],h,b(a[h],c)));return e?a:j?b.call(a):i?b(a[0],c):f};n.acceptData=function(a){return 1===a.nodeType||9===a.nodeType||!+a.nodeType};function K(){Object.defineProperty(this.cache={},0,{get:function(){return{}}}),this.expando=n.expando+K.uid++}K.uid=1,K.accepts=n.acceptData,K.prototype={key:function(a){if(!K.accepts(a))return 0;var b={},c=a[this.expando];if(!c){c=K.uid++;try{b[this.expando]={value:c},Object.defineProperties(a,b)}catch(d){b[this.expando]=c,n.extend(a,b)}}return this.cache[c]||(this.cache[c]={}),c},set:function(a,b,c){var d,e=this.key(a),f=this.cache[e];if("string"==typeof b)f[b]=c;else if(n.isEmptyObject(f))n.extend(this.cache[e],b);else for(d in b)f[d]=b[d];return f},get:function(a,b){var c=this.cache[this.key(a)];return void 0===b?c:c[b]},access:function(a,b,c){var d;return void 0===b||b&&"string"==typeof b&&void 0===c?(d=this.get(a,b),void 0!==d?d:this.get(a,n.camelCase(b))):(this.set(a,b,c),void 0!==c?c:b)},remove:function(a,b){var c,d,e,f=this.key(a),g=this.cache[f];if(void 0===b)this.cache[f]={};else{n.isArray(b)?d=b.concat(b.map(n.camelCase)):(e=n.camelCase(b),b in g?d=[b,e]:(d=e,d=d in g?[d]:d.match(E)||[])),c=d.length;while(c--)delete g[d[c]]}},hasData:function(a){return!n.isEmptyObject(this.cache[a[this.expando]]||{})},discard:function(a){a[this.expando]&&delete this.cache[a[this.expando]]}};var L=new K,M=new K,N=/^(?:\{[\w\W]*\}|\[[\w\W]*\])$/,O=/([A-Z])/g;function P(a,b,c){var d;if(void 0===c&&1===a.nodeType)if(d="data-"+b.replace(O,"-$1").toLowerCase(),c=a.getAttribute(d),"string"==typeof c){try{c="true"===c?!0:"false"===c?!1:"null"===c?null:+c+""===c?+c:N.test(c)?n.parseJSON(c):c}catch(e){}M.set(a,b,c)}else c=void 0;return c}n.extend({hasData:function(a){return M.hasData(a)||L.hasData(a)},data:function(a,b,c){return M.access(a,b,c)
},removeData:function(a,b){M.remove(a,b)},_data:function(a,b,c){return L.access(a,b,c)},_removeData:function(a,b){L.remove(a,b)}}),n.fn.extend({data:function(a,b){var c,d,e,f=this[0],g=f&&f.attributes;if(void 0===a){if(this.length&&(e=M.get(f),1===f.nodeType&&!L.get(f,"hasDataAttrs"))){c=g.length;while(c--)g[c]&&(d=g[c].name,0===d.indexOf("data-")&&(d=n.camelCase(d.slice(5)),P(f,d,e[d])));L.set(f,"hasDataAttrs",!0)}return e}return"object"==typeof a?this.each(function(){M.set(this,a)}):J(this,function(b){var c,d=n.camelCase(a);if(f&&void 0===b){if(c=M.get(f,a),void 0!==c)return c;if(c=M.get(f,d),void 0!==c)return c;if(c=P(f,d,void 0),void 0!==c)return c}else this.each(function(){var c=M.get(this,d);M.set(this,d,b),-1!==a.indexOf("-")&&void 0!==c&&M.set(this,a,b)})},null,b,arguments.length>1,null,!0)},removeData:function(a){return this.each(function(){M.remove(this,a)})}}),n.extend({queue:function(a,b,c){var d;return a?(b=(b||"fx")+"queue",d=L.get(a,b),c&&(!d||n.isArray(c)?d=L.access(a,b,n.makeArray(c)):d.push(c)),d||[]):void 0},dequeue:function(a,b){b=b||"fx";var c=n.queue(a,b),d=c.length,e=c.shift(),f=n._queueHooks(a,b),g=function(){n.dequeue(a,b)};"inprogress"===e&&(e=c.shift(),d--),e&&("fx"===b&&c.unshift("inprogress"),delete f.stop,e.call(a,g,f)),!d&&f&&f.empty.fire()},_queueHooks:function(a,b){var c=b+"queueHooks";return L.get(a,c)||L.access(a,c,{empty:n.Callbacks("once memory").add(function(){L.remove(a,[b+"queue",c])})})}}),n.fn.extend({queue:function(a,b){var c=2;return"string"!=typeof a&&(b=a,a="fx",c--),arguments.length<c?n.queue(this[0],a):void 0===b?this:this.each(function(){var c=n.queue(this,a,b);n._queueHooks(this,a),"fx"===a&&"inprogress"!==c[0]&&n.dequeue(this,a)})},dequeue:function(a){return this.each(function(){n.dequeue(this,a)})},clearQueue:function(a){return this.queue(a||"fx",[])},promise:function(a,b){var c,d=1,e=n.Deferred(),f=this,g=this.length,h=function(){--d||e.resolveWith(f,[f])};"string"!=typeof a&&(b=a,a=void 0),a=a||"fx";while(g--)c=L.get(f[g],a+"queueHooks"),c&&c.empty&&(d++,c.empty.add(h));return h(),e.promise(b)}});var Q=/[+-]?(?:\d*\.|)\d+(?:[eE][+-]?\d+|)/.source,R=["Top","Right","Bottom","Left"],S=function(a,b){return a=b||a,"none"===n.css(a,"display")||!n.contains(a.ownerDocument,a)},T=/^(?:checkbox|radio)$/i;!function(){var a=l.createDocumentFragment(),b=a.appendChild(l.createElement("div")),c=l.createElement("input");c.setAttribute("type","radio"),c.setAttribute("checked","checked"),c.setAttribute("name","t"),b.appendChild(c),k.checkClone=b.cloneNode(!0).cloneNode(!0).lastChild.checked,b.innerHTML="<textarea>x</textarea>",k.noCloneChecked=!!b.cloneNode(!0).lastChild.defaultValue}();var U="undefined";k.focusinBubbles="onfocusin"in a;var V=/^key/,W=/^(?:mouse|pointer|contextmenu)|click/,X=/^(?:focusinfocus|focusoutblur)$/,Y=/^([^.]*)(?:\.(.+)|)$/;function Z(){return!0}function $(){return!1}function _(){try{return l.activeElement}catch(a){}}n.event={global:{},add:function(a,b,c,d,e){var f,g,h,i,j,k,l,m,o,p,q,r=L.get(a);if(r){c.handler&&(f=c,c=f.handler,e=f.selector),c.guid||(c.guid=n.guid++),(i=r.events)||(i=r.events={}),(g=r.handle)||(g=r.handle=function(b){return typeof n!==U&&n.event.triggered!==b.type?n.event.dispatch.apply(a,arguments):void 0}),b=(b||"").match(E)||[""],j=b.length;while(j--)h=Y.exec(b[j])||[],o=q=h[1],p=(h[2]||"").split(".").sort(),o&&(l=n.event.special[o]||{},o=(e?l.delegateType:l.bindType)||o,l=n.event.special[o]||{},k=n.extend({type:o,origType:q,data:d,handler:c,guid:c.guid,selector:e,needsContext:e&&n.expr.match.needsContext.test(e),namespace:p.join(".")},f),(m=i[o])||(m=i[o]=[],m.delegateCount=0,l.setup&&l.setup.call(a,d,p,g)!==!1||a.addEventListener&&a.addEventListener(o,g,!1)),l.add&&(l.add.call(a,k),k.handler.guid||(k.handler.guid=c.guid)),e?m.splice(m.delegateCount++,0,k):m.push(k),n.event.global[o]=!0)}},remove:function(a,b,c,d,e){var f,g,h,i,j,k,l,m,o,p,q,r=L.hasData(a)&&L.get(a);if(r&&(i=r.events)){b=(b||"").match(E)||[""],j=b.length;while(j--)if(h=Y.exec(b[j])||[],o=q=h[1],p=(h[2]||"").split(".").sort(),o){l=n.event.special[o]||{},o=(d?l.delegateType:l.bindType)||o,m=i[o]||[],h=h[2]&&new RegExp("(^|\\.)"+p.join("\\.(?:.*\\.|)")+"(\\.|$)"),g=f=m.length;while(f--)k=m[f],!e&&q!==k.origType||c&&c.guid!==k.guid||h&&!h.test(k.namespace)||d&&d!==k.selector&&("**"!==d||!k.selector)||(m.splice(f,1),k.selector&&m.delegateCount--,l.remove&&l.remove.call(a,k));g&&!m.length&&(l.teardown&&l.teardown.call(a,p,r.handle)!==!1||n.removeEvent(a,o,r.handle),delete i[o])}else for(o in i)n.event.remove(a,o+b[j],c,d,!0);n.isEmptyObject(i)&&(delete r.handle,L.remove(a,"events"))}},trigger:function(b,c,d,e){var f,g,h,i,k,m,o,p=[d||l],q=j.call(b,"type")?b.type:b,r=j.call(b,"namespace")?b.namespace.split("."):[];if(g=h=d=d||l,3!==d.nodeType&&8!==d.nodeType&&!X.test(q+n.event.triggered)&&(q.indexOf(".")>=0&&(r=q.split("."),q=r.shift(),r.sort()),k=q.indexOf(":")<0&&"on"+q,b=b[n.expando]?b:new n.Event(q,"object"==typeof b&&b),b.isTrigger=e?2:3,b.namespace=r.join("."),b.namespace_re=b.namespace?new RegExp("(^|\\.)"+r.join("\\.(?:.*\\.|)")+"(\\.|$)"):null,b.result=void 0,b.target||(b.target=d),c=null==c?[b]:n.makeArray(c,[b]),o=n.event.special[q]||{},e||!o.trigger||o.trigger.apply(d,c)!==!1)){if(!e&&!o.noBubble&&!n.isWindow(d)){for(i=o.delegateType||q,X.test(i+q)||(g=g.parentNode);g;g=g.parentNode)p.push(g),h=g;h===(d.ownerDocument||l)&&p.push(h.defaultView||h.parentWindow||a)}f=0;while((g=p[f++])&&!b.isPropagationStopped())b.type=f>1?i:o.bindType||q,m=(L.get(g,"events")||{})[b.type]&&L.get(g,"handle"),m&&m.apply(g,c),m=k&&g[k],m&&m.apply&&n.acceptData(g)&&(b.result=m.apply(g,c),b.result===!1&&b.preventDefault());return b.type=q,e||b.isDefaultPrevented()||o._default&&o._default.apply(p.pop(),c)!==!1||!n.acceptData(d)||k&&n.isFunction(d[q])&&!n.isWindow(d)&&(h=d[k],h&&(d[k]=null),n.event.triggered=q,d[q](),n.event.triggered=void 0,h&&(d[k]=h)),b.result}},dispatch:function(a){a=n.event.fix(a);var b,c,e,f,g,h=[],i=d.call(arguments),j=(L.get(this,"events")||{})[a.type]||[],k=n.event.special[a.type]||{};if(i[0]=a,a.delegateTarget=this,!k.preDispatch||k.preDispatch.call(this,a)!==!1){h=n.event.handlers.call(this,a,j),b=0;while((f=h[b++])&&!a.isPropagationStopped()){a.currentTarget=f.elem,c=0;while((g=f.handlers[c++])&&!a.isImmediatePropagationStopped())(!a.namespace_re||a.namespace_re.test(g.namespace))&&(a.handleObj=g,a.data=g.data,e=((n.event.special[g.origType]||{}).handle||g.handler).apply(f.elem,i),void 0!==e&&(a.result=e)===!1&&(a.preventDefault(),a.stopPropagation()))}return k.postDispatch&&k.postDispatch.call(this,a),a.result}},handlers:function(a,b){var c,d,e,f,g=[],h=b.delegateCount,i=a.target;if(h&&i.nodeType&&(!a.button||"click"!==a.type))for(;i!==this;i=i.parentNode||this)if(i.disabled!==!0||"click"!==a.type){for(d=[],c=0;h>c;c++)f=b[c],e=f.selector+" ",void 0===d[e]&&(d[e]=f.needsContext?n(e,this).index(i)>=0:n.find(e,this,null,[i]).length),d[e]&&d.push(f);d.length&&g.push({elem:i,handlers:d})}return h<b.length&&g.push({elem:this,handlers:b.slice(h)}),g},props:"altKey bubbles cancelable ctrlKey currentTarget eventPhase metaKey relatedTarget shiftKey target timeStamp view which".split(" "),fixHooks:{},keyHooks:{props:"char charCode key keyCode".split(" "),filter:function(a,b){return null==a.which&&(a.which=null!=b.charCode?b.charCode:b.keyCode),a}},mouseHooks:{props:"button buttons clientX clientY offsetX offsetY pageX pageY screenX screenY toElement".split(" "),filter:function(a,b){var c,d,e,f=b.button;return null==a.pageX&&null!=b.clientX&&(c=a.target.ownerDocument||l,d=c.documentElement,e=c.body,a.pageX=b.clientX+(d&&d.scrollLeft||e&&e.scrollLeft||0)-(d&&d.clientLeft||e&&e.clientLeft||0),a.pageY=b.clientY+(d&&d.scrollTop||e&&e.scrollTop||0)-(d&&d.clientTop||e&&e.clientTop||0)),a.which||void 0===f||(a.which=1&f?1:2&f?3:4&f?2:0),a}},fix:function(a){if(a[n.expando])return a;var b,c,d,e=a.type,f=a,g=this.fixHooks[e];g||(this.fixHooks[e]=g=W.test(e)?this.mouseHooks:V.test(e)?this.keyHooks:{}),d=g.props?this.props.concat(g.props):this.props,a=new n.Event(f),b=d.length;while(b--)c=d[b],a[c]=f[c];return a.target||(a.target=l),3===a.target.nodeType&&(a.target=a.target.parentNode),g.filter?g.filter(a,f):a},special:{load:{noBubble:!0},focus:{trigger:function(){return this!==_()&&this.focus?(this.focus(),!1):void 0},delegateType:"focusin"},blur:{trigger:function(){return this===_()&&this.blur?(this.blur(),!1):void 0},delegateType:"focusout"},click:{trigger:function(){return"checkbox"===this.type&&this.click&&n.nodeName(this,"input")?(this.click(),!1):void 0},_default:function(a){return n.nodeName(a.target,"a")}},beforeunload:{postDispatch:function(a){void 0!==a.result&&a.originalEvent&&(a.originalEvent.returnValue=a.result)}}},simulate:function(a,b,c,d){var e=n.extend(new n.Event,c,{type:a,isSimulated:!0,originalEvent:{}});d?n.event.trigger(e,null,b):n.event.dispatch.call(b,e),e.isDefaultPrevented()&&c.preventDefault()}},n.removeEvent=function(a,b,c){a.removeEventListener&&a.removeEventListener(b,c,!1)},n.Event=function(a,b){return this instanceof n.Event?(a&&a.type?(this.originalEvent=a,this.type=a.type,this.isDefaultPrevented=a.defaultPrevented||void 0===a.defaultPrevented&&a.returnValue===!1?Z:$):this.type=a,b&&n.extend(this,b),this.timeStamp=a&&a.timeStamp||n.now(),void(this[n.expando]=!0)):new n.Event(a,b)},n.Event.prototype={isDefaultPrevented:$,isPropagationStopped:$,isImmediatePropagationStopped:$,preventDefault:function(){var a=this.originalEvent;this.isDefaultPrevented=Z,a&&a.preventDefault&&a.preventDefault()},stopPropagation:function(){var a=this.originalEvent;this.isPropagationStopped=Z,a&&a.stopPropagation&&a.stopPropagation()},stopImmediatePropagation:function(){var a=this.originalEvent;this.isImmediatePropagationStopped=Z,a&&a.stopImmediatePropagation&&a.stopImmediatePropagation(),this.stopPropagation()}},n.each({mouseenter:"mouseover",mouseleave:"mouseout",pointerenter:"pointerover",pointerleave:"pointerout"},function(a,b){n.event.special[a]={delegateType:b,bindType:b,handle:function(a){var c,d=this,e=a.relatedTarget,f=a.handleObj;return(!e||e!==d&&!n.contains(d,e))&&(a.type=f.origType,c=f.handler.apply(this,arguments),a.type=b),c}}}),k.focusinBubbles||n.each({focus:"focusin",blur:"focusout"},function(a,b){var c=function(a){n.event.simulate(b,a.target,n.event.fix(a),!0)};n.event.special[b]={setup:function(){var d=this.ownerDocument||this,e=L.access(d,b);e||d.addEventListener(a,c,!0),L.access(d,b,(e||0)+1)},teardown:function(){var d=this.ownerDocument||this,e=L.access(d,b)-1;e?L.access(d,b,e):(d.removeEventListener(a,c,!0),L.remove(d,b))}}}),n.fn.extend({on:function(a,b,c,d,e){var f,g;if("object"==typeof a){"string"!=typeof b&&(c=c||b,b=void 0);for(g in a)this.on(g,b,c,a[g],e);return this}if(null==c&&null==d?(d=b,c=b=void 0):null==d&&("string"==typeof b?(d=c,c=void 0):(d=c,c=b,b=void 0)),d===!1)d=$;else if(!d)return this;return 1===e&&(f=d,d=function(a){return n().off(a),f.apply(this,arguments)},d.guid=f.guid||(f.guid=n.guid++)),this.each(function(){n.event.add(this,a,d,c,b)})},one:function(a,b,c,d){return this.on(a,b,c,d,1)},off:function(a,b,c){var d,e;if(a&&a.preventDefault&&a.handleObj)return d=a.handleObj,n(a.delegateTarget).off(d.namespace?d.origType+"."+d.namespace:d.origType,d.selector,d.handler),this;if("object"==typeof a){for(e in a)this.off(e,b,a[e]);return this}return(b===!1||"function"==typeof b)&&(c=b,b=void 0),c===!1&&(c=$),this.each(function(){n.event.remove(this,a,c,b)})},trigger:function(a,b){return this.each(function(){n.event.trigger(a,b,this)})},triggerHandler:function(a,b){var c=this[0];return c?n.event.trigger(a,b,c,!0):void 0}});var ab=/<(?!area|br|col|embed|hr|img|input|link|meta|param)(([\w:]+)[^>]*)\/>/gi,bb=/<([\w:]+)/,cb=/<|&#?\w+;/,db=/<(?:script|style|link)/i,eb=/checked\s*(?:[^=]|=\s*.checked.)/i,fb=/^$|\/(?:java|ecma)script/i,gb=/^true\/(.*)/,hb=/^\s*<!(?:\[CDATA\[|--)|(?:\]\]|--)>\s*$/g,ib={option:[1,"<select multiple='multiple'>","</select>"],thead:[1,"<table>","</table>"],col:[2,"<table><colgroup>","</colgroup></table>"],tr:[2,"<table><tbody>","</tbody></table>"],td:[3,"<table><tbody><tr>","</tr></tbody></table>"],_default:[0,"",""]};ib.optgroup=ib.option,ib.tbody=ib.tfoot=ib.colgroup=ib.caption=ib.thead,ib.th=ib.td;function jb(a,b){return n.nodeName(a,"table")&&n.nodeName(11!==b.nodeType?b:b.firstChild,"tr")?a.getElementsByTagName("tbody")[0]||a.appendChild(a.ownerDocument.createElement("tbody")):a}function kb(a){return a.type=(null!==a.getAttribute("type"))+"/"+a.type,a}function lb(a){var b=gb.exec(a.type);return b?a.type=b[1]:a.removeAttribute("type"),a}function mb(a,b){for(var c=0,d=a.length;d>c;c++)L.set(a[c],"globalEval",!b||L.get(b[c],"globalEval"))}function nb(a,b){var c,d,e,f,g,h,i,j;if(1===b.nodeType){if(L.hasData(a)&&(f=L.access(a),g=L.set(b,f),j=f.events)){delete g.handle,g.events={};for(e in j)for(c=0,d=j[e].length;d>c;c++)n.event.add(b,e,j[e][c])}M.hasData(a)&&(h=M.access(a),i=n.extend({},h),M.set(b,i))}}function ob(a,b){var c=a.getElementsByTagName?a.getElementsByTagName(b||"*"):a.querySelectorAll?a.querySelectorAll(b||"*"):[];return void 0===b||b&&n.nodeName(a,b)?n.merge([a],c):c}function pb(a,b){var c=b.nodeName.toLowerCase();"input"===c&&T.test(a.type)?b.checked=a.checked:("input"===c||"textarea"===c)&&(b.defaultValue=a.defaultValue)}n.extend({clone:function(a,b,c){var d,e,f,g,h=a.cloneNode(!0),i=n.contains(a.ownerDocument,a);if(!(k.noCloneChecked||1!==a.nodeType&&11!==a.nodeType||n.isXMLDoc(a)))for(g=ob(h),f=ob(a),d=0,e=f.length;e>d;d++)pb(f[d],g[d]);if(b)if(c)for(f=f||ob(a),g=g||ob(h),d=0,e=f.length;e>d;d++)nb(f[d],g[d]);else nb(a,h);return g=ob(h,"script"),g.length>0&&mb(g,!i&&ob(a,"script")),h},buildFragment:function(a,b,c,d){for(var e,f,g,h,i,j,k=b.createDocumentFragment(),l=[],m=0,o=a.length;o>m;m++)if(e=a[m],e||0===e)if("object"===n.type(e))n.merge(l,e.nodeType?[e]:e);else if(cb.test(e)){f=f||k.appendChild(b.createElement("div")),g=(bb.exec(e)||["",""])[1].toLowerCase(),h=ib[g]||ib._default,f.innerHTML=h[1]+e.replace(ab,"<$1></$2>")+h[2],j=h[0];while(j--)f=f.lastChild;n.merge(l,f.childNodes),f=k.firstChild,f.textContent=""}else l.push(b.createTextNode(e));k.textContent="",m=0;while(e=l[m++])if((!d||-1===n.inArray(e,d))&&(i=n.contains(e.ownerDocument,e),f=ob(k.appendChild(e),"script"),i&&mb(f),c)){j=0;while(e=f[j++])fb.test(e.type||"")&&c.push(e)}return k},cleanData:function(a){for(var b,c,d,e,f=n.event.special,g=0;void 0!==(c=a[g]);g++){if(n.acceptData(c)&&(e=c[L.expando],e&&(b=L.cache[e]))){if(b.events)for(d in b.events)f[d]?n.event.remove(c,d):n.removeEvent(c,d,b.handle);L.cache[e]&&delete L.cache[e]}delete M.cache[c[M.expando]]}}}),n.fn.extend({text:function(a){return J(this,function(a){return void 0===a?n.text(this):this.empty().each(function(){(1===this.nodeType||11===this.nodeType||9===this.nodeType)&&(this.textContent=a)})},null,a,arguments.length)},append:function(){return this.domManip(arguments,function(a){if(1===this.nodeType||11===this.nodeType||9===this.nodeType){var b=jb(this,a);b.appendChild(a)}})},prepend:function(){return this.domManip(arguments,function(a){if(1===this.nodeType||11===this.nodeType||9===this.nodeType){var b=jb(this,a);b.insertBefore(a,b.firstChild)}})},before:function(){return this.domManip(arguments,function(a){this.parentNode&&this.parentNode.insertBefore(a,this)})},after:function(){return this.domManip(arguments,function(a){this.parentNode&&this.parentNode.insertBefore(a,this.nextSibling)})},remove:function(a,b){for(var c,d=a?n.filter(a,this):this,e=0;null!=(c=d[e]);e++)b||1!==c.nodeType||n.cleanData(ob(c)),c.parentNode&&(b&&n.contains(c.ownerDocument,c)&&mb(ob(c,"script")),c.parentNode.removeChild(c));return this},empty:function(){for(var a,b=0;null!=(a=this[b]);b++)1===a.nodeType&&(n.cleanData(ob(a,!1)),a.textContent="");return this},clone:function(a,b){return a=null==a?!1:a,b=null==b?a:b,this.map(function(){return n.clone(this,a,b)})},html:function(a){return J(this,function(a){var b=this[0]||{},c=0,d=this.length;if(void 0===a&&1===b.nodeType)return b.innerHTML;if("string"==typeof a&&!db.test(a)&&!ib[(bb.exec(a)||["",""])[1].toLowerCase()]){a=a.replace(ab,"<$1></$2>");try{for(;d>c;c++)b=this[c]||{},1===b.nodeType&&(n.cleanData(ob(b,!1)),b.innerHTML=a);b=0}catch(e){}}b&&this.empty().append(a)},null,a,arguments.length)},replaceWith:function(){var a=arguments[0];return this.domManip(arguments,function(b){a=this.parentNode,n.cleanData(ob(this)),a&&a.replaceChild(b,this)}),a&&(a.length||a.nodeType)?this:this.remove()},detach:function(a){return this.remove(a,!0)},domManip:function(a,b){a=e.apply([],a);var c,d,f,g,h,i,j=0,l=this.length,m=this,o=l-1,p=a[0],q=n.isFunction(p);if(q||l>1&&"string"==typeof p&&!k.checkClone&&eb.test(p))return this.each(function(c){var d=m.eq(c);q&&(a[0]=p.call(this,c,d.html())),d.domManip(a,b)});if(l&&(c=n.buildFragment(a,this[0].ownerDocument,!1,this),d=c.firstChild,1===c.childNodes.length&&(c=d),d)){for(f=n.map(ob(c,"script"),kb),g=f.length;l>j;j++)h=c,j!==o&&(h=n.clone(h,!0,!0),g&&n.merge(f,ob(h,"script"))),b.call(this[j],h,j);if(g)for(i=f[f.length-1].ownerDocument,n.map(f,lb),j=0;g>j;j++)h=f[j],fb.test(h.type||"")&&!L.access(h,"globalEval")&&n.contains(i,h)&&(h.src?n._evalUrl&&n._evalUrl(h.src):n.globalEval(h.textContent.replace(hb,"")))}return this}}),n.each({appendTo:"append",prependTo:"prepend",insertBefore:"before",insertAfter:"after",replaceAll:"replaceWith"},function(a,b){n.fn[a]=function(a){for(var c,d=[],e=n(a),g=e.length-1,h=0;g>=h;h++)c=h===g?this:this.clone(!0),n(e[h])[b](c),f.apply(d,c.get());return this.pushStack(d)}});var qb,rb={};function sb(b,c){var d,e=n(c.createElement(b)).appendTo(c.body),f=a.getDefaultComputedStyle&&(d=a.getDefaultComputedStyle(e[0]))?d.display:n.css(e[0],"display");return e.detach(),f}function tb(a){var b=l,c=rb[a];return c||(c=sb(a,b),"none"!==c&&c||(qb=(qb||n("<iframe frameborder='0' width='0' height='0'/>")).appendTo(b.documentElement),b=qb[0].contentDocument,b.write(),b.close(),c=sb(a,b),qb.detach()),rb[a]=c),c}var ub=/^margin/,vb=new RegExp("^("+Q+")(?!px)[a-z%]+$","i"),wb=function(b){return b.ownerDocument.defaultView.opener?b.ownerDocument.defaultView.getComputedStyle(b,null):a.getComputedStyle(b,null)};function xb(a,b,c){var d,e,f,g,h=a.style;return c=c||wb(a),c&&(g=c.getPropertyValue(b)||c[b]),c&&(""!==g||n.contains(a.ownerDocument,a)||(g=n.style(a,b)),vb.test(g)&&ub.test(b)&&(d=h.width,e=h.minWidth,f=h.maxWidth,h.minWidth=h.maxWidth=h.width=g,g=c.width,h.width=d,h.minWidth=e,h.maxWidth=f)),void 0!==g?g+"":g}function yb(a,b){return{get:function(){return a()?void delete this.get:(this.get=b).apply(this,arguments)}}}!function(){var b,c,d=l.documentElement,e=l.createElement("div"),f=l.createElement("div");if(f.style){f.style.backgroundClip="content-box",f.cloneNode(!0).style.backgroundClip="",k.clearCloneStyle="content-box"===f.style.backgroundClip,e.style.cssText="border:0;width:0;height:0;top:0;left:-9999px;margin-top:1px;position:absolute",e.appendChild(f);function g(){f.style.cssText="-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box;display:block;margin-top:1%;top:1%;border:1px;padding:1px;width:4px;position:absolute",f.innerHTML="",d.appendChild(e);var g=a.getComputedStyle(f,null);b="1%"!==g.top,c="4px"===g.width,d.removeChild(e)}a.getComputedStyle&&n.extend(k,{pixelPosition:function(){return g(),b},boxSizingReliable:function(){return null==c&&g(),c},reliableMarginRight:function(){var b,c=f.appendChild(l.createElement("div"));return c.style.cssText=f.style.cssText="-webkit-box-sizing:content-box;-moz-box-sizing:content-box;box-sizing:content-box;display:block;margin:0;border:0;padding:0",c.style.marginRight=c.style.width="0",f.style.width="1px",d.appendChild(e),b=!parseFloat(a.getComputedStyle(c,null).marginRight),d.removeChild(e),f.removeChild(c),b}})}}(),n.swap=function(a,b,c,d){var e,f,g={};for(f in b)g[f]=a.style[f],a.style[f]=b[f];e=c.apply(a,d||[]);for(f in b)a.style[f]=g[f];return e};var zb=/^(none|table(?!-c[ea]).+)/,Ab=new RegExp("^("+Q+")(.*)$","i"),Bb=new RegExp("^([+-])=("+Q+")","i"),Cb={position:"absolute",visibility:"hidden",display:"block"},Db={letterSpacing:"0",fontWeight:"400"},Eb=["Webkit","O","Moz","ms"];function Fb(a,b){if(b in a)return b;var c=b[0].toUpperCase()+b.slice(1),d=b,e=Eb.length;while(e--)if(b=Eb[e]+c,b in a)return b;return d}function Gb(a,b,c){var d=Ab.exec(b);return d?Math.max(0,d[1]-(c||0))+(d[2]||"px"):b}function Hb(a,b,c,d,e){for(var f=c===(d?"border":"content")?4:"width"===b?1:0,g=0;4>f;f+=2)"margin"===c&&(g+=n.css(a,c+R[f],!0,e)),d?("content"===c&&(g-=n.css(a,"padding"+R[f],!0,e)),"margin"!==c&&(g-=n.css(a,"border"+R[f]+"Width",!0,e))):(g+=n.css(a,"padding"+R[f],!0,e),"padding"!==c&&(g+=n.css(a,"border"+R[f]+"Width",!0,e)));return g}function Ib(a,b,c){var d=!0,e="width"===b?a.offsetWidth:a.offsetHeight,f=wb(a),g="border-box"===n.css(a,"boxSizing",!1,f);if(0>=e||null==e){if(e=xb(a,b,f),(0>e||null==e)&&(e=a.style[b]),vb.test(e))return e;d=g&&(k.boxSizingReliable()||e===a.style[b]),e=parseFloat(e)||0}return e+Hb(a,b,c||(g?"border":"content"),d,f)+"px"}function Jb(a,b){for(var c,d,e,f=[],g=0,h=a.length;h>g;g++)d=a[g],d.style&&(f[g]=L.get(d,"olddisplay"),c=d.style.display,b?(f[g]||"none"!==c||(d.style.display=""),""===d.style.display&&S(d)&&(f[g]=L.access(d,"olddisplay",tb(d.nodeName)))):(e=S(d),"none"===c&&e||L.set(d,"olddisplay",e?c:n.css(d,"display"))));for(g=0;h>g;g++)d=a[g],d.style&&(b&&"none"!==d.style.display&&""!==d.style.display||(d.style.display=b?f[g]||"":"none"));return a}n.extend({cssHooks:{opacity:{get:function(a,b){if(b){var c=xb(a,"opacity");return""===c?"1":c}}}},cssNumber:{columnCount:!0,fillOpacity:!0,flexGrow:!0,flexShrink:!0,fontWeight:!0,lineHeight:!0,opacity:!0,order:!0,orphans:!0,widows:!0,zIndex:!0,zoom:!0},cssProps:{"float":"cssFloat"},style:function(a,b,c,d){if(a&&3!==a.nodeType&&8!==a.nodeType&&a.style){var e,f,g,h=n.camelCase(b),i=a.style;return b=n.cssProps[h]||(n.cssProps[h]=Fb(i,h)),g=n.cssHooks[b]||n.cssHooks[h],void 0===c?g&&"get"in g&&void 0!==(e=g.get(a,!1,d))?e:i[b]:(f=typeof c,"string"===f&&(e=Bb.exec(c))&&(c=(e[1]+1)*e[2]+parseFloat(n.css(a,b)),f="number"),null!=c&&c===c&&("number"!==f||n.cssNumber[h]||(c+="px"),k.clearCloneStyle||""!==c||0!==b.indexOf("background")||(i[b]="inherit"),g&&"set"in g&&void 0===(c=g.set(a,c,d))||(i[b]=c)),void 0)}},css:function(a,b,c,d){var e,f,g,h=n.camelCase(b);return b=n.cssProps[h]||(n.cssProps[h]=Fb(a.style,h)),g=n.cssHooks[b]||n.cssHooks[h],g&&"get"in g&&(e=g.get(a,!0,c)),void 0===e&&(e=xb(a,b,d)),"normal"===e&&b in Db&&(e=Db[b]),""===c||c?(f=parseFloat(e),c===!0||n.isNumeric(f)?f||0:e):e}}),n.each(["height","width"],function(a,b){n.cssHooks[b]={get:function(a,c,d){return c?zb.test(n.css(a,"display"))&&0===a.offsetWidth?n.swap(a,Cb,function(){return Ib(a,b,d)}):Ib(a,b,d):void 0},set:function(a,c,d){var e=d&&wb(a);return Gb(a,c,d?Hb(a,b,d,"border-box"===n.css(a,"boxSizing",!1,e),e):0)}}}),n.cssHooks.marginRight=yb(k.reliableMarginRight,function(a,b){return b?n.swap(a,{display:"inline-block"},xb,[a,"marginRight"]):void 0}),n.each({margin:"",padding:"",border:"Width"},function(a,b){n.cssHooks[a+b]={expand:function(c){for(var d=0,e={},f="string"==typeof c?c.split(" "):[c];4>d;d++)e[a+R[d]+b]=f[d]||f[d-2]||f[0];return e}},ub.test(a)||(n.cssHooks[a+b].set=Gb)}),n.fn.extend({css:function(a,b){return J(this,function(a,b,c){var d,e,f={},g=0;if(n.isArray(b)){for(d=wb(a),e=b.length;e>g;g++)f[b[g]]=n.css(a,b[g],!1,d);return f}return void 0!==c?n.style(a,b,c):n.css(a,b)},a,b,arguments.length>1)},show:function(){return Jb(this,!0)},hide:function(){return Jb(this)},toggle:function(a){return"boolean"==typeof a?a?this.show():this.hide():this.each(function(){S(this)?n(this).show():n(this).hide()})}});function Kb(a,b,c,d,e){return new Kb.prototype.init(a,b,c,d,e)}n.Tween=Kb,Kb.prototype={constructor:Kb,init:function(a,b,c,d,e,f){this.elem=a,this.prop=c,this.easing=e||"swing",this.options=b,this.start=this.now=this.cur(),this.end=d,this.unit=f||(n.cssNumber[c]?"":"px")},cur:function(){var a=Kb.propHooks[this.prop];return a&&a.get?a.get(this):Kb.propHooks._default.get(this)},run:function(a){var b,c=Kb.propHooks[this.prop];return this.pos=b=this.options.duration?n.easing[this.easing](a,this.options.duration*a,0,1,this.options.duration):a,this.now=(this.end-this.start)*b+this.start,this.options.step&&this.options.step.call(this.elem,this.now,this),c&&c.set?c.set(this):Kb.propHooks._default.set(this),this}},Kb.prototype.init.prototype=Kb.prototype,Kb.propHooks={_default:{get:function(a){var b;return null==a.elem[a.prop]||a.elem.style&&null!=a.elem.style[a.prop]?(b=n.css(a.elem,a.prop,""),b&&"auto"!==b?b:0):a.elem[a.prop]},set:function(a){n.fx.step[a.prop]?n.fx.step[a.prop](a):a.elem.style&&(null!=a.elem.style[n.cssProps[a.prop]]||n.cssHooks[a.prop])?n.style(a.elem,a.prop,a.now+a.unit):a.elem[a.prop]=a.now}}},Kb.propHooks.scrollTop=Kb.propHooks.scrollLeft={set:function(a){a.elem.nodeType&&a.elem.parentNode&&(a.elem[a.prop]=a.now)}},n.easing={linear:function(a){return a},swing:function(a){return.5-Math.cos(a*Math.PI)/2}},n.fx=Kb.prototype.init,n.fx.step={};var Lb,Mb,Nb=/^(?:toggle|show|hide)$/,Ob=new RegExp("^(?:([+-])=|)("+Q+")([a-z%]*)$","i"),Pb=/queueHooks$/,Qb=[Vb],Rb={"*":[function(a,b){var c=this.createTween(a,b),d=c.cur(),e=Ob.exec(b),f=e&&e[3]||(n.cssNumber[a]?"":"px"),g=(n.cssNumber[a]||"px"!==f&&+d)&&Ob.exec(n.css(c.elem,a)),h=1,i=20;if(g&&g[3]!==f){f=f||g[3],e=e||[],g=+d||1;do h=h||".5",g/=h,n.style(c.elem,a,g+f);while(h!==(h=c.cur()/d)&&1!==h&&--i)}return e&&(g=c.start=+g||+d||0,c.unit=f,c.end=e[1]?g+(e[1]+1)*e[2]:+e[2]),c}]};function Sb(){return setTimeout(function(){Lb=void 0}),Lb=n.now()}function Tb(a,b){var c,d=0,e={height:a};for(b=b?1:0;4>d;d+=2-b)c=R[d],e["margin"+c]=e["padding"+c]=a;return b&&(e.opacity=e.width=a),e}function Ub(a,b,c){for(var d,e=(Rb[b]||[]).concat(Rb["*"]),f=0,g=e.length;g>f;f++)if(d=e[f].call(c,b,a))return d}function Vb(a,b,c){var d,e,f,g,h,i,j,k,l=this,m={},o=a.style,p=a.nodeType&&S(a),q=L.get(a,"fxshow");c.queue||(h=n._queueHooks(a,"fx"),null==h.unqueued&&(h.unqueued=0,i=h.empty.fire,h.empty.fire=function(){h.unqueued||i()}),h.unqueued++,l.always(function(){l.always(function(){h.unqueued--,n.queue(a,"fx").length||h.empty.fire()})})),1===a.nodeType&&("height"in b||"width"in b)&&(c.overflow=[o.overflow,o.overflowX,o.overflowY],j=n.css(a,"display"),k="none"===j?L.get(a,"olddisplay")||tb(a.nodeName):j,"inline"===k&&"none"===n.css(a,"float")&&(o.display="inline-block")),c.overflow&&(o.overflow="hidden",l.always(function(){o.overflow=c.overflow[0],o.overflowX=c.overflow[1],o.overflowY=c.overflow[2]}));for(d in b)if(e=b[d],Nb.exec(e)){if(delete b[d],f=f||"toggle"===e,e===(p?"hide":"show")){if("show"!==e||!q||void 0===q[d])continue;p=!0}m[d]=q&&q[d]||n.style(a,d)}else j=void 0;if(n.isEmptyObject(m))"inline"===("none"===j?tb(a.nodeName):j)&&(o.display=j);else{q?"hidden"in q&&(p=q.hidden):q=L.access(a,"fxshow",{}),f&&(q.hidden=!p),p?n(a).show():l.done(function(){n(a).hide()}),l.done(function(){var b;L.remove(a,"fxshow");for(b in m)n.style(a,b,m[b])});for(d in m)g=Ub(p?q[d]:0,d,l),d in q||(q[d]=g.start,p&&(g.end=g.start,g.start="width"===d||"height"===d?1:0))}}function Wb(a,b){var c,d,e,f,g;for(c in a)if(d=n.camelCase(c),e=b[d],f=a[c],n.isArray(f)&&(e=f[1],f=a[c]=f[0]),c!==d&&(a[d]=f,delete a[c]),g=n.cssHooks[d],g&&"expand"in g){f=g.expand(f),delete a[d];for(c in f)c in a||(a[c]=f[c],b[c]=e)}else b[d]=e}function Xb(a,b,c){var d,e,f=0,g=Qb.length,h=n.Deferred().always(function(){delete i.elem}),i=function(){if(e)return!1;for(var b=Lb||Sb(),c=Math.max(0,j.startTime+j.duration-b),d=c/j.duration||0,f=1-d,g=0,i=j.tweens.length;i>g;g++)j.tweens[g].run(f);return h.notifyWith(a,[j,f,c]),1>f&&i?c:(h.resolveWith(a,[j]),!1)},j=h.promise({elem:a,props:n.extend({},b),opts:n.extend(!0,{specialEasing:{}},c),originalProperties:b,originalOptions:c,startTime:Lb||Sb(),duration:c.duration,tweens:[],createTween:function(b,c){var d=n.Tween(a,j.opts,b,c,j.opts.specialEasing[b]||j.opts.easing);return j.tweens.push(d),d},stop:function(b){var c=0,d=b?j.tweens.length:0;if(e)return this;for(e=!0;d>c;c++)j.tweens[c].run(1);return b?h.resolveWith(a,[j,b]):h.rejectWith(a,[j,b]),this}}),k=j.props;for(Wb(k,j.opts.specialEasing);g>f;f++)if(d=Qb[f].call(j,a,k,j.opts))return d;return n.map(k,Ub,j),n.isFunction(j.opts.start)&&j.opts.start.call(a,j),n.fx.timer(n.extend(i,{elem:a,anim:j,queue:j.opts.queue})),j.progress(j.opts.progress).done(j.opts.done,j.opts.complete).fail(j.opts.fail).always(j.opts.always)}n.Animation=n.extend(Xb,{tweener:function(a,b){n.isFunction(a)?(b=a,a=["*"]):a=a.split(" ");for(var c,d=0,e=a.length;e>d;d++)c=a[d],Rb[c]=Rb[c]||[],Rb[c].unshift(b)},prefilter:function(a,b){b?Qb.unshift(a):Qb.push(a)}}),n.speed=function(a,b,c){var d=a&&"object"==typeof a?n.extend({},a):{complete:c||!c&&b||n.isFunction(a)&&a,duration:a,easing:c&&b||b&&!n.isFunction(b)&&b};return d.duration=n.fx.off?0:"number"==typeof d.duration?d.duration:d.duration in n.fx.speeds?n.fx.speeds[d.duration]:n.fx.speeds._default,(null==d.queue||d.queue===!0)&&(d.queue="fx"),d.old=d.complete,d.complete=function(){n.isFunction(d.old)&&d.old.call(this),d.queue&&n.dequeue(this,d.queue)},d},n.fn.extend({fadeTo:function(a,b,c,d){return this.filter(S).css("opacity",0).show().end().animate({opacity:b},a,c,d)},animate:function(a,b,c,d){var e=n.isEmptyObject(a),f=n.speed(b,c,d),g=function(){var b=Xb(this,n.extend({},a),f);(e||L.get(this,"finish"))&&b.stop(!0)};return g.finish=g,e||f.queue===!1?this.each(g):this.queue(f.queue,g)},stop:function(a,b,c){var d=function(a){var b=a.stop;delete a.stop,b(c)};return"string"!=typeof a&&(c=b,b=a,a=void 0),b&&a!==!1&&this.queue(a||"fx",[]),this.each(function(){var b=!0,e=null!=a&&a+"queueHooks",f=n.timers,g=L.get(this);if(e)g[e]&&g[e].stop&&d(g[e]);else for(e in g)g[e]&&g[e].stop&&Pb.test(e)&&d(g[e]);for(e=f.length;e--;)f[e].elem!==this||null!=a&&f[e].queue!==a||(f[e].anim.stop(c),b=!1,f.splice(e,1));(b||!c)&&n.dequeue(this,a)})},finish:function(a){return a!==!1&&(a=a||"fx"),this.each(function(){var b,c=L.get(this),d=c[a+"queue"],e=c[a+"queueHooks"],f=n.timers,g=d?d.length:0;for(c.finish=!0,n.queue(this,a,[]),e&&e.stop&&e.stop.call(this,!0),b=f.length;b--;)f[b].elem===this&&f[b].queue===a&&(f[b].anim.stop(!0),f.splice(b,1));for(b=0;g>b;b++)d[b]&&d[b].finish&&d[b].finish.call(this);delete c.finish})}}),n.each(["toggle","show","hide"],function(a,b){var c=n.fn[b];n.fn[b]=function(a,d,e){return null==a||"boolean"==typeof a?c.apply(this,arguments):this.animate(Tb(b,!0),a,d,e)}}),n.each({slideDown:Tb("show"),slideUp:Tb("hide"),slideToggle:Tb("toggle"),fadeIn:{opacity:"show"},fadeOut:{opacity:"hide"},fadeToggle:{opacity:"toggle"}},function(a,b){n.fn[a]=function(a,c,d){return this.animate(b,a,c,d)}}),n.timers=[],n.fx.tick=function(){var a,b=0,c=n.timers;for(Lb=n.now();b<c.length;b++)a=c[b],a()||c[b]!==a||c.splice(b--,1);c.length||n.fx.stop(),Lb=void 0},n.fx.timer=function(a){n.timers.push(a),a()?n.fx.start():n.timers.pop()},n.fx.interval=13,n.fx.start=function(){Mb||(Mb=setInterval(n.fx.tick,n.fx.interval))},n.fx.stop=function(){clearInterval(Mb),Mb=null},n.fx.speeds={slow:600,fast:200,_default:400},n.fn.delay=function(a,b){return a=n.fx?n.fx.speeds[a]||a:a,b=b||"fx",this.queue(b,function(b,c){var d=setTimeout(b,a);c.stop=function(){clearTimeout(d)}})},function(){var a=l.createElement("input"),b=l.createElement("select"),c=b.appendChild(l.createElement("option"));a.type="checkbox",k.checkOn=""!==a.value,k.optSelected=c.selected,b.disabled=!0,k.optDisabled=!c.disabled,a=l.createElement("input"),a.value="t",a.type="radio",k.radioValue="t"===a.value}();var Yb,Zb,$b=n.expr.attrHandle;n.fn.extend({attr:function(a,b){return J(this,n.attr,a,b,arguments.length>1)},removeAttr:function(a){return this.each(function(){n.removeAttr(this,a)})}}),n.extend({attr:function(a,b,c){var d,e,f=a.nodeType;if(a&&3!==f&&8!==f&&2!==f)return typeof a.getAttribute===U?n.prop(a,b,c):(1===f&&n.isXMLDoc(a)||(b=b.toLowerCase(),d=n.attrHooks[b]||(n.expr.match.bool.test(b)?Zb:Yb)),void 0===c?d&&"get"in d&&null!==(e=d.get(a,b))?e:(e=n.find.attr(a,b),null==e?void 0:e):null!==c?d&&"set"in d&&void 0!==(e=d.set(a,c,b))?e:(a.setAttribute(b,c+""),c):void n.removeAttr(a,b))
},removeAttr:function(a,b){var c,d,e=0,f=b&&b.match(E);if(f&&1===a.nodeType)while(c=f[e++])d=n.propFix[c]||c,n.expr.match.bool.test(c)&&(a[d]=!1),a.removeAttribute(c)},attrHooks:{type:{set:function(a,b){if(!k.radioValue&&"radio"===b&&n.nodeName(a,"input")){var c=a.value;return a.setAttribute("type",b),c&&(a.value=c),b}}}}}),Zb={set:function(a,b,c){return b===!1?n.removeAttr(a,c):a.setAttribute(c,c),c}},n.each(n.expr.match.bool.source.match(/\w+/g),function(a,b){var c=$b[b]||n.find.attr;$b[b]=function(a,b,d){var e,f;return d||(f=$b[b],$b[b]=e,e=null!=c(a,b,d)?b.toLowerCase():null,$b[b]=f),e}});var _b=/^(?:input|select|textarea|button)$/i;n.fn.extend({prop:function(a,b){return J(this,n.prop,a,b,arguments.length>1)},removeProp:function(a){return this.each(function(){delete this[n.propFix[a]||a]})}}),n.extend({propFix:{"for":"htmlFor","class":"className"},prop:function(a,b,c){var d,e,f,g=a.nodeType;if(a&&3!==g&&8!==g&&2!==g)return f=1!==g||!n.isXMLDoc(a),f&&(b=n.propFix[b]||b,e=n.propHooks[b]),void 0!==c?e&&"set"in e&&void 0!==(d=e.set(a,c,b))?d:a[b]=c:e&&"get"in e&&null!==(d=e.get(a,b))?d:a[b]},propHooks:{tabIndex:{get:function(a){return a.hasAttribute("tabindex")||_b.test(a.nodeName)||a.href?a.tabIndex:-1}}}}),k.optSelected||(n.propHooks.selected={get:function(a){var b=a.parentNode;return b&&b.parentNode&&b.parentNode.selectedIndex,null}}),n.each(["tabIndex","readOnly","maxLength","cellSpacing","cellPadding","rowSpan","colSpan","useMap","frameBorder","contentEditable"],function(){n.propFix[this.toLowerCase()]=this});var ac=/[\t\r\n\f]/g;n.fn.extend({addClass:function(a){var b,c,d,e,f,g,h="string"==typeof a&&a,i=0,j=this.length;if(n.isFunction(a))return this.each(function(b){n(this).addClass(a.call(this,b,this.className))});if(h)for(b=(a||"").match(E)||[];j>i;i++)if(c=this[i],d=1===c.nodeType&&(c.className?(" "+c.className+" ").replace(ac," "):" ")){f=0;while(e=b[f++])d.indexOf(" "+e+" ")<0&&(d+=e+" ");g=n.trim(d),c.className!==g&&(c.className=g)}return this},removeClass:function(a){var b,c,d,e,f,g,h=0===arguments.length||"string"==typeof a&&a,i=0,j=this.length;if(n.isFunction(a))return this.each(function(b){n(this).removeClass(a.call(this,b,this.className))});if(h)for(b=(a||"").match(E)||[];j>i;i++)if(c=this[i],d=1===c.nodeType&&(c.className?(" "+c.className+" ").replace(ac," "):"")){f=0;while(e=b[f++])while(d.indexOf(" "+e+" ")>=0)d=d.replace(" "+e+" "," ");g=a?n.trim(d):"",c.className!==g&&(c.className=g)}return this},toggleClass:function(a,b){var c=typeof a;return"boolean"==typeof b&&"string"===c?b?this.addClass(a):this.removeClass(a):this.each(n.isFunction(a)?function(c){n(this).toggleClass(a.call(this,c,this.className,b),b)}:function(){if("string"===c){var b,d=0,e=n(this),f=a.match(E)||[];while(b=f[d++])e.hasClass(b)?e.removeClass(b):e.addClass(b)}else(c===U||"boolean"===c)&&(this.className&&L.set(this,"__className__",this.className),this.className=this.className||a===!1?"":L.get(this,"__className__")||"")})},hasClass:function(a){for(var b=" "+a+" ",c=0,d=this.length;d>c;c++)if(1===this[c].nodeType&&(" "+this[c].className+" ").replace(ac," ").indexOf(b)>=0)return!0;return!1}});var bc=/\r/g;n.fn.extend({val:function(a){var b,c,d,e=this[0];{if(arguments.length)return d=n.isFunction(a),this.each(function(c){var e;1===this.nodeType&&(e=d?a.call(this,c,n(this).val()):a,null==e?e="":"number"==typeof e?e+="":n.isArray(e)&&(e=n.map(e,function(a){return null==a?"":a+""})),b=n.valHooks[this.type]||n.valHooks[this.nodeName.toLowerCase()],b&&"set"in b&&void 0!==b.set(this,e,"value")||(this.value=e))});if(e)return b=n.valHooks[e.type]||n.valHooks[e.nodeName.toLowerCase()],b&&"get"in b&&void 0!==(c=b.get(e,"value"))?c:(c=e.value,"string"==typeof c?c.replace(bc,""):null==c?"":c)}}}),n.extend({valHooks:{option:{get:function(a){var b=n.find.attr(a,"value");return null!=b?b:n.trim(n.text(a))}},select:{get:function(a){for(var b,c,d=a.options,e=a.selectedIndex,f="select-one"===a.type||0>e,g=f?null:[],h=f?e+1:d.length,i=0>e?h:f?e:0;h>i;i++)if(c=d[i],!(!c.selected&&i!==e||(k.optDisabled?c.disabled:null!==c.getAttribute("disabled"))||c.parentNode.disabled&&n.nodeName(c.parentNode,"optgroup"))){if(b=n(c).val(),f)return b;g.push(b)}return g},set:function(a,b){var c,d,e=a.options,f=n.makeArray(b),g=e.length;while(g--)d=e[g],(d.selected=n.inArray(d.value,f)>=0)&&(c=!0);return c||(a.selectedIndex=-1),f}}}}),n.each(["radio","checkbox"],function(){n.valHooks[this]={set:function(a,b){return n.isArray(b)?a.checked=n.inArray(n(a).val(),b)>=0:void 0}},k.checkOn||(n.valHooks[this].get=function(a){return null===a.getAttribute("value")?"on":a.value})}),n.each("blur focus focusin focusout load resize scroll unload click dblclick mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave change select submit keydown keypress keyup error contextmenu".split(" "),function(a,b){n.fn[b]=function(a,c){return arguments.length>0?this.on(b,null,a,c):this.trigger(b)}}),n.fn.extend({hover:function(a,b){return this.mouseenter(a).mouseleave(b||a)},bind:function(a,b,c){return this.on(a,null,b,c)},unbind:function(a,b){return this.off(a,null,b)},delegate:function(a,b,c,d){return this.on(b,a,c,d)},undelegate:function(a,b,c){return 1===arguments.length?this.off(a,"**"):this.off(b,a||"**",c)}});var cc=n.now(),dc=/\?/;n.parseJSON=function(a){return JSON.parse(a+"")},n.parseXML=function(a){var b,c;if(!a||"string"!=typeof a)return null;try{c=new DOMParser,b=c.parseFromString(a,"text/xml")}catch(d){b=void 0}return(!b||b.getElementsByTagName("parsererror").length)&&n.error("Invalid XML: "+a),b};var ec=/#.*$/,fc=/([?&])_=[^&]*/,gc=/^(.*?):[ \t]*([^\r\n]*)$/gm,hc=/^(?:about|app|app-storage|.+-extension|file|res|widget):$/,ic=/^(?:GET|HEAD)$/,jc=/^\/\//,kc=/^([\w.+-]+:)(?:\/\/(?:[^\/?#]*@|)([^\/?#:]*)(?::(\d+)|)|)/,lc={},mc={},nc="*/".concat("*"),oc=a.location.href,pc=kc.exec(oc.toLowerCase())||[];function qc(a){return function(b,c){"string"!=typeof b&&(c=b,b="*");var d,e=0,f=b.toLowerCase().match(E)||[];if(n.isFunction(c))while(d=f[e++])"+"===d[0]?(d=d.slice(1)||"*",(a[d]=a[d]||[]).unshift(c)):(a[d]=a[d]||[]).push(c)}}function rc(a,b,c,d){var e={},f=a===mc;function g(h){var i;return e[h]=!0,n.each(a[h]||[],function(a,h){var j=h(b,c,d);return"string"!=typeof j||f||e[j]?f?!(i=j):void 0:(b.dataTypes.unshift(j),g(j),!1)}),i}return g(b.dataTypes[0])||!e["*"]&&g("*")}function sc(a,b){var c,d,e=n.ajaxSettings.flatOptions||{};for(c in b)void 0!==b[c]&&((e[c]?a:d||(d={}))[c]=b[c]);return d&&n.extend(!0,a,d),a}function tc(a,b,c){var d,e,f,g,h=a.contents,i=a.dataTypes;while("*"===i[0])i.shift(),void 0===d&&(d=a.mimeType||b.getResponseHeader("Content-Type"));if(d)for(e in h)if(h[e]&&h[e].test(d)){i.unshift(e);break}if(i[0]in c)f=i[0];else{for(e in c){if(!i[0]||a.converters[e+" "+i[0]]){f=e;break}g||(g=e)}f=f||g}return f?(f!==i[0]&&i.unshift(f),c[f]):void 0}function uc(a,b,c,d){var e,f,g,h,i,j={},k=a.dataTypes.slice();if(k[1])for(g in a.converters)j[g.toLowerCase()]=a.converters[g];f=k.shift();while(f)if(a.responseFields[f]&&(c[a.responseFields[f]]=b),!i&&d&&a.dataFilter&&(b=a.dataFilter(b,a.dataType)),i=f,f=k.shift())if("*"===f)f=i;else if("*"!==i&&i!==f){if(g=j[i+" "+f]||j["* "+f],!g)for(e in j)if(h=e.split(" "),h[1]===f&&(g=j[i+" "+h[0]]||j["* "+h[0]])){g===!0?g=j[e]:j[e]!==!0&&(f=h[0],k.unshift(h[1]));break}if(g!==!0)if(g&&a["throws"])b=g(b);else try{b=g(b)}catch(l){return{state:"parsererror",error:g?l:"No conversion from "+i+" to "+f}}}return{state:"success",data:b}}n.extend({active:0,lastModified:{},etag:{},ajaxSettings:{url:oc,type:"GET",isLocal:hc.test(pc[1]),global:!0,processData:!0,async:!0,contentType:"application/x-www-form-urlencoded; charset=UTF-8",accepts:{"*":nc,text:"text/plain",html:"text/html",xml:"application/xml, text/xml",json:"application/json, text/javascript"},contents:{xml:/xml/,html:/html/,json:/json/},responseFields:{xml:"responseXML",text:"responseText",json:"responseJSON"},converters:{"* text":String,"text html":!0,"text json":n.parseJSON,"text xml":n.parseXML},flatOptions:{url:!0,context:!0}},ajaxSetup:function(a,b){return b?sc(sc(a,n.ajaxSettings),b):sc(n.ajaxSettings,a)},ajaxPrefilter:qc(lc),ajaxTransport:qc(mc),ajax:function(a,b){"object"==typeof a&&(b=a,a=void 0),b=b||{};var c,d,e,f,g,h,i,j,k=n.ajaxSetup({},b),l=k.context||k,m=k.context&&(l.nodeType||l.jquery)?n(l):n.event,o=n.Deferred(),p=n.Callbacks("once memory"),q=k.statusCode||{},r={},s={},t=0,u="canceled",v={readyState:0,getResponseHeader:function(a){var b;if(2===t){if(!f){f={};while(b=gc.exec(e))f[b[1].toLowerCase()]=b[2]}b=f[a.toLowerCase()]}return null==b?null:b},getAllResponseHeaders:function(){return 2===t?e:null},setRequestHeader:function(a,b){var c=a.toLowerCase();return t||(a=s[c]=s[c]||a,r[a]=b),this},overrideMimeType:function(a){return t||(k.mimeType=a),this},statusCode:function(a){var b;if(a)if(2>t)for(b in a)q[b]=[q[b],a[b]];else v.always(a[v.status]);return this},abort:function(a){var b=a||u;return c&&c.abort(b),x(0,b),this}};if(o.promise(v).complete=p.add,v.success=v.done,v.error=v.fail,k.url=((a||k.url||oc)+"").replace(ec,"").replace(jc,pc[1]+"//"),k.type=b.method||b.type||k.method||k.type,k.dataTypes=n.trim(k.dataType||"*").toLowerCase().match(E)||[""],null==k.crossDomain&&(h=kc.exec(k.url.toLowerCase()),k.crossDomain=!(!h||h[1]===pc[1]&&h[2]===pc[2]&&(h[3]||("http:"===h[1]?"80":"443"))===(pc[3]||("http:"===pc[1]?"80":"443")))),k.data&&k.processData&&"string"!=typeof k.data&&(k.data=n.param(k.data,k.traditional)),rc(lc,k,b,v),2===t)return v;i=n.event&&k.global,i&&0===n.active++&&n.event.trigger("ajaxStart"),k.type=k.type.toUpperCase(),k.hasContent=!ic.test(k.type),d=k.url,k.hasContent||(k.data&&(d=k.url+=(dc.test(d)?"&":"?")+k.data,delete k.data),k.cache===!1&&(k.url=fc.test(d)?d.replace(fc,"$1_="+cc++):d+(dc.test(d)?"&":"?")+"_="+cc++)),k.ifModified&&(n.lastModified[d]&&v.setRequestHeader("If-Modified-Since",n.lastModified[d]),n.etag[d]&&v.setRequestHeader("If-None-Match",n.etag[d])),(k.data&&k.hasContent&&k.contentType!==!1||b.contentType)&&v.setRequestHeader("Content-Type",k.contentType),v.setRequestHeader("Accept",k.dataTypes[0]&&k.accepts[k.dataTypes[0]]?k.accepts[k.dataTypes[0]]+("*"!==k.dataTypes[0]?", "+nc+"; q=0.01":""):k.accepts["*"]);for(j in k.headers)v.setRequestHeader(j,k.headers[j]);if(k.beforeSend&&(k.beforeSend.call(l,v,k)===!1||2===t))return v.abort();u="abort";for(j in{success:1,error:1,complete:1})v[j](k[j]);if(c=rc(mc,k,b,v)){v.readyState=1,i&&m.trigger("ajaxSend",[v,k]),k.async&&k.timeout>0&&(g=setTimeout(function(){v.abort("timeout")},k.timeout));try{t=1,c.send(r,x)}catch(w){if(!(2>t))throw w;x(-1,w)}}else x(-1,"No Transport");function x(a,b,f,h){var j,r,s,u,w,x=b;2!==t&&(t=2,g&&clearTimeout(g),c=void 0,e=h||"",v.readyState=a>0?4:0,j=a>=200&&300>a||304===a,f&&(u=tc(k,v,f)),u=uc(k,u,v,j),j?(k.ifModified&&(w=v.getResponseHeader("Last-Modified"),w&&(n.lastModified[d]=w),w=v.getResponseHeader("etag"),w&&(n.etag[d]=w)),204===a||"HEAD"===k.type?x="nocontent":304===a?x="notmodified":(x=u.state,r=u.data,s=u.error,j=!s)):(s=x,(a||!x)&&(x="error",0>a&&(a=0))),v.status=a,v.statusText=(b||x)+"",j?o.resolveWith(l,[r,x,v]):o.rejectWith(l,[v,x,s]),v.statusCode(q),q=void 0,i&&m.trigger(j?"ajaxSuccess":"ajaxError",[v,k,j?r:s]),p.fireWith(l,[v,x]),i&&(m.trigger("ajaxComplete",[v,k]),--n.active||n.event.trigger("ajaxStop")))}return v},getJSON:function(a,b,c){return n.get(a,b,c,"json")},getScript:function(a,b){return n.get(a,void 0,b,"script")}}),n.each(["get","post"],function(a,b){n[b]=function(a,c,d,e){return n.isFunction(c)&&(e=e||d,d=c,c=void 0),n.ajax({url:a,type:b,dataType:e,data:c,success:d})}}),n._evalUrl=function(a){return n.ajax({url:a,type:"GET",dataType:"script",async:!1,global:!1,"throws":!0})},n.fn.extend({wrapAll:function(a){var b;return n.isFunction(a)?this.each(function(b){n(this).wrapAll(a.call(this,b))}):(this[0]&&(b=n(a,this[0].ownerDocument).eq(0).clone(!0),this[0].parentNode&&b.insertBefore(this[0]),b.map(function(){var a=this;while(a.firstElementChild)a=a.firstElementChild;return a}).append(this)),this)},wrapInner:function(a){return this.each(n.isFunction(a)?function(b){n(this).wrapInner(a.call(this,b))}:function(){var b=n(this),c=b.contents();c.length?c.wrapAll(a):b.append(a)})},wrap:function(a){var b=n.isFunction(a);return this.each(function(c){n(this).wrapAll(b?a.call(this,c):a)})},unwrap:function(){return this.parent().each(function(){n.nodeName(this,"body")||n(this).replaceWith(this.childNodes)}).end()}}),n.expr.filters.hidden=function(a){return a.offsetWidth<=0&&a.offsetHeight<=0},n.expr.filters.visible=function(a){return!n.expr.filters.hidden(a)};var vc=/%20/g,wc=/\[\]$/,xc=/\r?\n/g,yc=/^(?:submit|button|image|reset|file)$/i,zc=/^(?:input|select|textarea|keygen)/i;function Ac(a,b,c,d){var e;if(n.isArray(b))n.each(b,function(b,e){c||wc.test(a)?d(a,e):Ac(a+"["+("object"==typeof e?b:"")+"]",e,c,d)});else if(c||"object"!==n.type(b))d(a,b);else for(e in b)Ac(a+"["+e+"]",b[e],c,d)}n.param=function(a,b){var c,d=[],e=function(a,b){b=n.isFunction(b)?b():null==b?"":b,d[d.length]=encodeURIComponent(a)+"="+encodeURIComponent(b)};if(void 0===b&&(b=n.ajaxSettings&&n.ajaxSettings.traditional),n.isArray(a)||a.jquery&&!n.isPlainObject(a))n.each(a,function(){e(this.name,this.value)});else for(c in a)Ac(c,a[c],b,e);return d.join("&").replace(vc,"+")},n.fn.extend({serialize:function(){return n.param(this.serializeArray())},serializeArray:function(){return this.map(function(){var a=n.prop(this,"elements");return a?n.makeArray(a):this}).filter(function(){var a=this.type;return this.name&&!n(this).is(":disabled")&&zc.test(this.nodeName)&&!yc.test(a)&&(this.checked||!T.test(a))}).map(function(a,b){var c=n(this).val();return null==c?null:n.isArray(c)?n.map(c,function(a){return{name:b.name,value:a.replace(xc,"\r\n")}}):{name:b.name,value:c.replace(xc,"\r\n")}}).get()}}),n.ajaxSettings.xhr=function(){try{return new XMLHttpRequest}catch(a){}};var Bc=0,Cc={},Dc={0:200,1223:204},Ec=n.ajaxSettings.xhr();a.attachEvent&&a.attachEvent("onunload",function(){for(var a in Cc)Cc[a]()}),k.cors=!!Ec&&"withCredentials"in Ec,k.ajax=Ec=!!Ec,n.ajaxTransport(function(a){var b;return k.cors||Ec&&!a.crossDomain?{send:function(c,d){var e,f=a.xhr(),g=++Bc;if(f.open(a.type,a.url,a.async,a.username,a.password),a.xhrFields)for(e in a.xhrFields)f[e]=a.xhrFields[e];a.mimeType&&f.overrideMimeType&&f.overrideMimeType(a.mimeType),a.crossDomain||c["X-Requested-With"]||(c["X-Requested-With"]="XMLHttpRequest");for(e in c)f.setRequestHeader(e,c[e]);b=function(a){return function(){b&&(delete Cc[g],b=f.onload=f.onerror=null,"abort"===a?f.abort():"error"===a?d(f.status,f.statusText):d(Dc[f.status]||f.status,f.statusText,"string"==typeof f.responseText?{text:f.responseText}:void 0,f.getAllResponseHeaders()))}},f.onload=b(),f.onerror=b("error"),b=Cc[g]=b("abort");try{f.send(a.hasContent&&a.data||null)}catch(h){if(b)throw h}},abort:function(){b&&b()}}:void 0}),n.ajaxSetup({accepts:{script:"text/javascript, application/javascript, application/ecmascript, application/x-ecmascript"},contents:{script:/(?:java|ecma)script/},converters:{"text script":function(a){return n.globalEval(a),a}}}),n.ajaxPrefilter("script",function(a){void 0===a.cache&&(a.cache=!1),a.crossDomain&&(a.type="GET")}),n.ajaxTransport("script",function(a){if(a.crossDomain){var b,c;return{send:function(d,e){b=n("<script>").prop({async:!0,charset:a.scriptCharset,src:a.url}).on("load error",c=function(a){b.remove(),c=null,a&&e("error"===a.type?404:200,a.type)}),l.head.appendChild(b[0])},abort:function(){c&&c()}}}});var Fc=[],Gc=/(=)\?(?=&|$)|\?\?/;n.ajaxSetup({jsonp:"callback",jsonpCallback:function(){var a=Fc.pop()||n.expando+"_"+cc++;return this[a]=!0,a}}),n.ajaxPrefilter("json jsonp",function(b,c,d){var e,f,g,h=b.jsonp!==!1&&(Gc.test(b.url)?"url":"string"==typeof b.data&&!(b.contentType||"").indexOf("application/x-www-form-urlencoded")&&Gc.test(b.data)&&"data");return h||"jsonp"===b.dataTypes[0]?(e=b.jsonpCallback=n.isFunction(b.jsonpCallback)?b.jsonpCallback():b.jsonpCallback,h?b[h]=b[h].replace(Gc,"$1"+e):b.jsonp!==!1&&(b.url+=(dc.test(b.url)?"&":"?")+b.jsonp+"="+e),b.converters["script json"]=function(){return g||n.error(e+" was not called"),g[0]},b.dataTypes[0]="json",f=a[e],a[e]=function(){g=arguments},d.always(function(){a[e]=f,b[e]&&(b.jsonpCallback=c.jsonpCallback,Fc.push(e)),g&&n.isFunction(f)&&f(g[0]),g=f=void 0}),"script"):void 0}),n.parseHTML=function(a,b,c){if(!a||"string"!=typeof a)return null;"boolean"==typeof b&&(c=b,b=!1),b=b||l;var d=v.exec(a),e=!c&&[];return d?[b.createElement(d[1])]:(d=n.buildFragment([a],b,e),e&&e.length&&n(e).remove(),n.merge([],d.childNodes))};var Hc=n.fn.load;n.fn.load=function(a,b,c){if("string"!=typeof a&&Hc)return Hc.apply(this,arguments);var d,e,f,g=this,h=a.indexOf(" ");return h>=0&&(d=n.trim(a.slice(h)),a=a.slice(0,h)),n.isFunction(b)?(c=b,b=void 0):b&&"object"==typeof b&&(e="POST"),g.length>0&&n.ajax({url:a,type:e,dataType:"html",data:b}).done(function(a){f=arguments,g.html(d?n("<div>").append(n.parseHTML(a)).find(d):a)}).complete(c&&function(a,b){g.each(c,f||[a.responseText,b,a])}),this},n.each(["ajaxStart","ajaxStop","ajaxComplete","ajaxError","ajaxSuccess","ajaxSend"],function(a,b){n.fn[b]=function(a){return this.on(b,a)}}),n.expr.filters.animated=function(a){return n.grep(n.timers,function(b){return a===b.elem}).length};var Ic=a.document.documentElement;function Jc(a){return n.isWindow(a)?a:9===a.nodeType&&a.defaultView}n.offset={setOffset:function(a,b,c){var d,e,f,g,h,i,j,k=n.css(a,"position"),l=n(a),m={};"static"===k&&(a.style.position="relative"),h=l.offset(),f=n.css(a,"top"),i=n.css(a,"left"),j=("absolute"===k||"fixed"===k)&&(f+i).indexOf("auto")>-1,j?(d=l.position(),g=d.top,e=d.left):(g=parseFloat(f)||0,e=parseFloat(i)||0),n.isFunction(b)&&(b=b.call(a,c,h)),null!=b.top&&(m.top=b.top-h.top+g),null!=b.left&&(m.left=b.left-h.left+e),"using"in b?b.using.call(a,m):l.css(m)}},n.fn.extend({offset:function(a){if(arguments.length)return void 0===a?this:this.each(function(b){n.offset.setOffset(this,a,b)});var b,c,d=this[0],e={top:0,left:0},f=d&&d.ownerDocument;if(f)return b=f.documentElement,n.contains(b,d)?(typeof d.getBoundingClientRect!==U&&(e=d.getBoundingClientRect()),c=Jc(f),{top:e.top+c.pageYOffset-b.clientTop,left:e.left+c.pageXOffset-b.clientLeft}):e},position:function(){if(this[0]){var a,b,c=this[0],d={top:0,left:0};return"fixed"===n.css(c,"position")?b=c.getBoundingClientRect():(a=this.offsetParent(),b=this.offset(),n.nodeName(a[0],"html")||(d=a.offset()),d.top+=n.css(a[0],"borderTopWidth",!0),d.left+=n.css(a[0],"borderLeftWidth",!0)),{top:b.top-d.top-n.css(c,"marginTop",!0),left:b.left-d.left-n.css(c,"marginLeft",!0)}}},offsetParent:function(){return this.map(function(){var a=this.offsetParent||Ic;while(a&&!n.nodeName(a,"html")&&"static"===n.css(a,"position"))a=a.offsetParent;return a||Ic})}}),n.each({scrollLeft:"pageXOffset",scrollTop:"pageYOffset"},function(b,c){var d="pageYOffset"===c;n.fn[b]=function(e){return J(this,function(b,e,f){var g=Jc(b);return void 0===f?g?g[c]:b[e]:void(g?g.scrollTo(d?a.pageXOffset:f,d?f:a.pageYOffset):b[e]=f)},b,e,arguments.length,null)}}),n.each(["top","left"],function(a,b){n.cssHooks[b]=yb(k.pixelPosition,function(a,c){return c?(c=xb(a,b),vb.test(c)?n(a).position()[b]+"px":c):void 0})}),n.each({Height:"height",Width:"width"},function(a,b){n.each({padding:"inner"+a,content:b,"":"outer"+a},function(c,d){n.fn[d]=function(d,e){var f=arguments.length&&(c||"boolean"!=typeof d),g=c||(d===!0||e===!0?"margin":"border");return J(this,function(b,c,d){var e;return n.isWindow(b)?b.document.documentElement["client"+a]:9===b.nodeType?(e=b.documentElement,Math.max(b.body["scroll"+a],e["scroll"+a],b.body["offset"+a],e["offset"+a],e["client"+a])):void 0===d?n.css(b,c,g):n.style(b,c,d,g)},b,f?d:void 0,f,null)}})}),n.fn.size=function(){return this.length},n.fn.andSelf=n.fn.addBack,"function"==typeof define&&define.amd&&define("jquery",[],function(){return n});var Kc=a.jQuery,Lc=a.$;return n.noConflict=function(b){return a.$===n&&(a.$=Lc),b&&a.jQuery===n&&(a.jQuery=Kc),n},typeof b===U&&(a.jQuery=a.$=n),n});

    <?php
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}


function resource_4aee6e3a3f21404e70f423b056813084() {
    ob_start(); ?>
    // CodeMirror, copyright (c) by Marijn Haverbeke and others
// Distributed under an MIT license: http://codemirror.net/LICENSE

(function(mod) {
    if (typeof exports == "object" && typeof module == "object") // CommonJS
        mod(require("../../lib/codemirror"));
    else if (typeof define == "function" && define.amd) // AMD
        define(["../../lib/codemirror"], mod);
    else // Plain browser env
        mod(CodeMirror);
})(function(CodeMirror) {
    "use strict";

    var htmlConfig = {
        autoSelfClosers: {'area': true, 'base': true, 'br': true, 'col': true, 'command': true,
            'embed': true, 'frame': true, 'hr': true, 'img': true, 'input': true,
            'keygen': true, 'link': true, 'meta': true, 'param': true, 'source': true,
            'track': true, 'wbr': true, 'menuitem': true},
        implicitlyClosed: {'dd': true, 'li': true, 'optgroup': true, 'option': true, 'p': true,
            'rp': true, 'rt': true, 'tbody': true, 'td': true, 'tfoot': true,
            'th': true, 'tr': true},
        contextGrabbers: {
            'dd': {'dd': true, 'dt': true},
            'dt': {'dd': true, 'dt': true},
            'li': {'li': true},
            'option': {'option': true, 'optgroup': true},
            'optgroup': {'optgroup': true},
            'p': {'address': true, 'article': true, 'aside': true, 'blockquote': true, 'dir': true,
                'div': true, 'dl': true, 'fieldset': true, 'footer': true, 'form': true,
                'h1': true, 'h2': true, 'h3': true, 'h4': true, 'h5': true, 'h6': true,
                'header': true, 'hgroup': true, 'hr': true, 'menu': true, 'nav': true, 'ol': true,
                'p': true, 'pre': true, 'section': true, 'table': true, 'ul': true},
            'rp': {'rp': true, 'rt': true},
            'rt': {'rp': true, 'rt': true},
            'tbody': {'tbody': true, 'tfoot': true},
            'td': {'td': true, 'th': true},
            'tfoot': {'tbody': true},
            'th': {'td': true, 'th': true},
            'thead': {'tbody': true, 'tfoot': true},
            'tr': {'tr': true}
        },
        doNotIndent: {"pre": true},
        allowUnquoted: true,
        allowMissing: true,
        caseFold: true
    }

    var xmlConfig = {
        autoSelfClosers: {},
        implicitlyClosed: {},
        contextGrabbers: {},
        doNotIndent: {},
        allowUnquoted: false,
        allowMissing: false,
        caseFold: false
    }

    CodeMirror.defineMode("xml", function(editorConf, config_) {
        var indentUnit = editorConf.indentUnit
        var config = {}
        var defaults = config_.htmlMode ? htmlConfig : xmlConfig
        for (var prop in defaults) config[prop] = defaults[prop]
        for (var prop in config_) config[prop] = config_[prop]

        // Return variables for tokenizers
        var type, setStyle;

        function inText(stream, state) {
            function chain(parser) {
                state.tokenize = parser;
                return parser(stream, state);
            }

            var ch = stream.next();
            if (ch == "<") {
                if (stream.eat("!")) {
                    if (stream.eat("[")) {
                        if (stream.match("CDATA[")) return chain(inBlock("atom", "]]>"));
                        else return null;
                    } else if (stream.match("--")) {
                        return chain(inBlock("comment", "-->"));
                    } else if (stream.match("DOCTYPE", true, true)) {
                        stream.eatWhile(/[\w\._\-]/);
                        return chain(doctype(1));
                    } else {
                        return null;
                    }
                } else if (stream.eat("?")) {
                    stream.eatWhile(/[\w\._\-]/);
                    state.tokenize = inBlock("meta", "?>");
                    return "meta";
                } else {
                    type = stream.eat("/") ? "closeTag" : "openTag";
                    state.tokenize = inTag;
                    return "tag bracket";
                }
            } else if (ch == "&") {
                var ok;
                if (stream.eat("#")) {
                    if (stream.eat("x")) {
                        ok = stream.eatWhile(/[a-fA-F\d]/) && stream.eat(";");
                    } else {
                        ok = stream.eatWhile(/[\d]/) && stream.eat(";");
                    }
                } else {
                    ok = stream.eatWhile(/[\w\.\-:]/) && stream.eat(";");
                }
                return ok ? "atom" : "error";
            } else {
                stream.eatWhile(/[^&<]/);
                return null;
            }
        }
        inText.isInText = true;

        function inTag(stream, state) {
            var ch = stream.next();
            if (ch == ">" || (ch == "/" && stream.eat(">"))) {
                state.tokenize = inText;
                type = ch == ">" ? "endTag" : "selfcloseTag";
                return "tag bracket";
            } else if (ch == "=") {
                type = "equals";
                return null;
            } else if (ch == "<") {
                state.tokenize = inText;
                state.state = baseState;
                state.tagName = state.tagStart = null;
                var next = state.tokenize(stream, state);
                return next ? next + " tag error" : "tag error";
            } else if (/[\'\"]/.test(ch)) {
                state.tokenize = inAttribute(ch);
                state.stringStartCol = stream.column();
                return state.tokenize(stream, state);
            } else {
                stream.match(/^[^\s\u00a0=<>\"\']*[^\s\u00a0=<>\"\'\/]/);
                return "word";
            }
        }

        function inAttribute(quote) {
            var closure = function(stream, state) {
                while (!stream.eol()) {
                    if (stream.next() == quote) {
                        state.tokenize = inTag;
                        break;
                    }
                }
                return "string";
            };
            closure.isInAttribute = true;
            return closure;
        }

        function inBlock(style, terminator) {
            return function(stream, state) {
                while (!stream.eol()) {
                    if (stream.match(terminator)) {
                        state.tokenize = inText;
                        break;
                    }
                    stream.next();
                }
                return style;
            };
        }
        function doctype(depth) {
            return function(stream, state) {
                var ch;
                while ((ch = stream.next()) != null) {
                    if (ch == "<") {
                        state.tokenize = doctype(depth + 1);
                        return state.tokenize(stream, state);
                    } else if (ch == ">") {
                        if (depth == 1) {
                            state.tokenize = inText;
                            break;
                        } else {
                            state.tokenize = doctype(depth - 1);
                            return state.tokenize(stream, state);
                        }
                    }
                }
                return "meta";
            };
        }

        function Context(state, tagName, startOfLine) {
            this.prev = state.context;
            this.tagName = tagName;
            this.indent = state.indented;
            this.startOfLine = startOfLine;
            if (config.doNotIndent.hasOwnProperty(tagName) || (state.context && state.context.noIndent))
                this.noIndent = true;
        }
        function popContext(state) {
            if (state.context) state.context = state.context.prev;
        }
        function maybePopContext(state, nextTagName) {
            var parentTagName;
            while (true) {
                if (!state.context) {
                    return;
                }
                parentTagName = state.context.tagName;
                if (!config.contextGrabbers.hasOwnProperty(parentTagName) ||
                    !config.contextGrabbers[parentTagName].hasOwnProperty(nextTagName)) {
                    return;
                }
                popContext(state);
            }
        }

        function baseState(type, stream, state) {
            if (type == "openTag") {
                state.tagStart = stream.column();
                return tagNameState;
            } else if (type == "closeTag") {
                return closeTagNameState;
            } else {
                return baseState;
            }
        }
        function tagNameState(type, stream, state) {
            if (type == "word") {
                state.tagName = stream.current();
                setStyle = "tag";
                return attrState;
            } else {
                setStyle = "error";
                return tagNameState;
            }
        }
        function closeTagNameState(type, stream, state) {
            if (type == "word") {
                var tagName = stream.current();
                if (state.context && state.context.tagName != tagName &&
                    config.implicitlyClosed.hasOwnProperty(state.context.tagName))
                    popContext(state);
                if ((state.context && state.context.tagName == tagName) || config.matchClosing === false) {
                    setStyle = "tag";
                    return closeState;
                } else {
                    setStyle = "tag error";
                    return closeStateErr;
                }
            } else {
                setStyle = "error";
                return closeStateErr;
            }
        }

        function closeState(type, _stream, state) {
            if (type != "endTag") {
                setStyle = "error";
                return closeState;
            }
            popContext(state);
            return baseState;
        }
        function closeStateErr(type, stream, state) {
            setStyle = "error";
            return closeState(type, stream, state);
        }

        function attrState(type, _stream, state) {
            if (type == "word") {
                setStyle = "attribute";
                return attrEqState;
            } else if (type == "endTag" || type == "selfcloseTag") {
                var tagName = state.tagName, tagStart = state.tagStart;
                state.tagName = state.tagStart = null;
                if (type == "selfcloseTag" ||
                    config.autoSelfClosers.hasOwnProperty(tagName)) {
                    maybePopContext(state, tagName);
                } else {
                    maybePopContext(state, tagName);
                    state.context = new Context(state, tagName, tagStart == state.indented);
                }
                return baseState;
            }
            setStyle = "error";
            return attrState;
        }
        function attrEqState(type, stream, state) {
            if (type == "equals") return attrValueState;
            if (!config.allowMissing) setStyle = "error";
            return attrState(type, stream, state);
        }
        function attrValueState(type, stream, state) {
            if (type == "string") return attrContinuedState;
            if (type == "word" && config.allowUnquoted) {setStyle = "string"; return attrState;}
            setStyle = "error";
            return attrState(type, stream, state);
        }
        function attrContinuedState(type, stream, state) {
            if (type == "string") return attrContinuedState;
            return attrState(type, stream, state);
        }

        return {
            startState: function(baseIndent) {
                var state = {tokenize: inText,
                    state: baseState,
                    indented: baseIndent || 0,
                    tagName: null, tagStart: null,
                    context: null}
                if (baseIndent != null) state.baseIndent = baseIndent
                return state
            },

            token: function(stream, state) {
                if (!state.tagName && stream.sol())
                    state.indented = stream.indentation();

                if (stream.eatSpace()) return null;
                type = null;
                var style = state.tokenize(stream, state);
                if ((style || type) && style != "comment") {
                    setStyle = null;
                    state.state = state.state(type || style, stream, state);
                    if (setStyle)
                        style = setStyle == "error" ? style + " error" : setStyle;
                }
                return style;
            },

            indent: function(state, textAfter, fullLine) {
                var context = state.context;
                // Indent multi-line strings (e.g. css).
                if (state.tokenize.isInAttribute) {
                    if (state.tagStart == state.indented)
                        return state.stringStartCol + 1;
                    else
                        return state.indented + indentUnit;
                }
                if (context && context.noIndent) return CodeMirror.Pass;
                if (state.tokenize != inTag && state.tokenize != inText)
                    return fullLine ? fullLine.match(/^(\s*)/)[0].length : 0;
                // Indent the starts of attribute names.
                if (state.tagName) {
                    if (config.multilineTagIndentPastTag !== false)
                        return state.tagStart + state.tagName.length + 2;
                    else
                        return state.tagStart + indentUnit * (config.multilineTagIndentFactor || 1);
                }
                if (config.alignCDATA && /<!\[CDATA\[/.test(textAfter)) return 0;
                var tagAfter = textAfter && /^<(\/)?([\w_:\.-]*)/.exec(textAfter);
                if (tagAfter && tagAfter[1]) { // Closing tag spotted
                    while (context) {
                        if (context.tagName == tagAfter[2]) {
                            context = context.prev;
                            break;
                        } else if (config.implicitlyClosed.hasOwnProperty(context.tagName)) {
                            context = context.prev;
                        } else {
                            break;
                        }
                    }
                } else if (tagAfter) { // Opening tag spotted
                    while (context) {
                        var grabbers = config.contextGrabbers[context.tagName];
                        if (grabbers && grabbers.hasOwnProperty(tagAfter[2]))
                            context = context.prev;
                        else
                            break;
                    }
                }
                while (context && context.prev && !context.startOfLine)
                    context = context.prev;
                if (context) return context.indent + indentUnit;
                else return state.baseIndent || 0;
            },

            electricInput: /<\/[\s\w:]+>$/,
            blockCommentStart: "<!--",
            blockCommentEnd: "-->",

            configuration: config.htmlMode ? "html" : "xml",
            helperType: config.htmlMode ? "html" : "xml",

            skipAttribute: function(state) {
                if (state.state == attrValueState)
                    state.state = attrState
            }
        };
    });

    CodeMirror.defineMIME("text/xml", "xml");
    CodeMirror.defineMIME("application/xml", "xml");
    if (!CodeMirror.mimeModes.hasOwnProperty("text/html"))
        CodeMirror.defineMIME("text/html", {name: "xml", htmlMode: true});

});
    <?php
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}


function resource_be2bea665c2d59bf5897cd3e2325d9d8() {
    ob_start(); ?>
    /* BASICS */

.CodeMirror {
    /* Set height, width, borders, and global font properties here */
    font-family: monospace;
    height: 300px;
    color: black;
}

/* PADDING */

.CodeMirror-lines {
    padding: 4px 0; /* Vertical padding around content */
}
.CodeMirror pre {
    padding: 0 4px; /* Horizontal padding of content */
}

.CodeMirror-scrollbar-filler, .CodeMirror-gutter-filler {
    background-color: white; /* The little square between H and V scrollbars */
}

/* GUTTER */

.CodeMirror-gutters {
    border-right: 1px solid #ddd;
    background-color: #f7f7f7;
    white-space: nowrap;
}
.CodeMirror-linenumbers {}
.CodeMirror-linenumber {
    padding: 0 3px 0 5px;
    min-width: 20px;
    text-align: right;
    color: #999;
    white-space: nowrap;
}

.CodeMirror-guttermarker { color: black; }
.CodeMirror-guttermarker-subtle { color: #999; }

/* CURSOR */

.CodeMirror-cursor {
    border-left: 1px solid black;
    border-right: none;
    width: 0;
}
/* Shown when moving in bi-directional text */
.CodeMirror div.CodeMirror-secondarycursor {
    border-left: 1px solid silver;
}
.cm-fat-cursor .CodeMirror-cursor {
    width: auto;
    border: 0 !important;
    background: #7e7;
}
.cm-fat-cursor div.CodeMirror-cursors {
    z-index: 1;
}

.cm-animate-fat-cursor {
    width: auto;
    border: 0;
    -webkit-animation: blink 1.06s steps(1) infinite;
    -moz-animation: blink 1.06s steps(1) infinite;
    animation: blink 1.06s steps(1) infinite;
    background-color: #7e7;
}
@-moz-keyframes blink {
    0% {}
    50% { background-color: transparent; }
    100% {}
}
@-webkit-keyframes blink {
    0% {}
    50% { background-color: transparent; }
    100% {}
}
@keyframes blink {
    0% {}
    50% { background-color: transparent; }
    100% {}
}

/* Can style cursor different in overwrite (non-insert) mode */
.CodeMirror-overwrite .CodeMirror-cursor {}

.cm-tab { display: inline-block; text-decoration: inherit; }

.CodeMirror-rulers {
    position: absolute;
    left: 0; right: 0; top: -50px; bottom: -20px;
    overflow: hidden;
}
.CodeMirror-ruler {
    border-left: 1px solid #ccc;
    top: 0; bottom: 0;
    position: absolute;
}

/* DEFAULT THEME */

.cm-s-default .cm-header {color: blue;}
.cm-s-default .cm-quote {color: #090;}
.cm-negative {color: #d44;}
.cm-positive {color: #292;}
.cm-header, .cm-strong {font-weight: bold;}
.cm-em {font-style: italic;}
.cm-link {text-decoration: underline;}
.cm-strikethrough {text-decoration: line-through;}

.cm-s-default .cm-keyword {color: #708;}
.cm-s-default .cm-atom {color: #219;}
.cm-s-default .cm-number {color: #164;}
.cm-s-default .cm-def {color: #00f;}
.cm-s-default .cm-variable,
.cm-s-default .cm-punctuation,
.cm-s-default .cm-property,
.cm-s-default .cm-operator {}
.cm-s-default .cm-variable-2 {color: #05a;}
.cm-s-default .cm-variable-3 {color: #085;}
.cm-s-default .cm-comment {color: #a50;}
.cm-s-default .cm-string {color: #a11;}
.cm-s-default .cm-string-2 {color: #f50;}
.cm-s-default .cm-meta {color: #555;}
.cm-s-default .cm-qualifier {color: #555;}
.cm-s-default .cm-builtin {color: #30a;}
.cm-s-default .cm-bracket {color: #997;}
.cm-s-default .cm-tag {color: #170;}
.cm-s-default .cm-attribute {color: #00c;}
.cm-s-default .cm-hr {color: #999;}
.cm-s-default .cm-link {color: #00c;}

.cm-s-default .cm-error {color: #f00;}
.cm-invalidchar {color: #f00;}

.CodeMirror-composing { border-bottom: 2px solid; }

/* Default styles for common addons */

div.CodeMirror span.CodeMirror-matchingbracket {color: #0f0;}
div.CodeMirror span.CodeMirror-nonmatchingbracket {color: #f22;}
.CodeMirror-matchingtag { background: rgba(255, 150, 0, .3); }
.CodeMirror-activeline-background {background: #e8f2ff;}

/* STOP */

/* The rest of this file contains styles related to the mechanics of
   the editor. You probably shouldn't touch them. */

.CodeMirror {
    position: relative;
    overflow: hidden;
    background: white;
}

.CodeMirror-scroll {
    overflow: scroll !important; /* Things will break if this is overridden */
    /* 30px is the magic margin used to hide the element's real scrollbars */
    /* See overflow: hidden in .CodeMirror */
    margin-bottom: -30px; margin-right: -30px;
    padding-bottom: 30px;
    height: 100%;
    outline: none; /* Prevent dragging from highlighting the element */
    position: relative;
}
.CodeMirror-sizer {
    position: relative;
    border-right: 30px solid transparent;
}

/* The fake, visible scrollbars. Used to force redraw during scrolling
   before actual scrolling happens, thus preventing shaking and
   flickering artifacts. */
.CodeMirror-vscrollbar, .CodeMirror-hscrollbar, .CodeMirror-scrollbar-filler, .CodeMirror-gutter-filler {
    position: absolute;
    z-index: 6;
    display: none;
}
.CodeMirror-vscrollbar {
    right: 0; top: 0;
    overflow-x: hidden;
    overflow-y: scroll;
}
.CodeMirror-hscrollbar {
    bottom: 0; left: 0;
    overflow-y: hidden;
    overflow-x: scroll;
}
.CodeMirror-scrollbar-filler {
    right: 0; bottom: 0;
}
.CodeMirror-gutter-filler {
    left: 0; bottom: 0;
}

.CodeMirror-gutters {
    position: absolute; left: 0; top: 0;
    min-height: 100%;
    z-index: 3;
}
.CodeMirror-gutter {
    white-space: normal;
    height: 100%;
    display: inline-block;
    vertical-align: top;
    margin-bottom: -30px;
}
.CodeMirror-gutter-wrapper {
    position: absolute;
    z-index: 4;
    background: none !important;
    border: none !important;
}
.CodeMirror-gutter-background {
    position: absolute;
    top: 0; bottom: 0;
    z-index: 4;
}
.CodeMirror-gutter-elt {
    position: absolute;
    cursor: default;
    z-index: 4;
}
.CodeMirror-gutter-wrapper {
    -webkit-user-select: none;
    -moz-user-select: none;
    user-select: none;
}

.CodeMirror-lines {
    cursor: text;
    min-height: 1px; /* prevents collapsing before first draw */
}
.CodeMirror pre {
    /* Reset some styles that the rest of the page might have set */
    -moz-border-radius: 0; -webkit-border-radius: 0; border-radius: 0;
    border-width: 0;
    background: transparent;
    font-family: inherit;
    font-size: inherit;
    margin: 0;
    white-space: pre;
    word-wrap: normal;
    line-height: inherit;
    color: inherit;
    z-index: 2;
    position: relative;
    overflow: visible;
    -webkit-tap-highlight-color: transparent;
    -webkit-font-variant-ligatures: none;
    font-variant-ligatures: none;
}
.CodeMirror-wrap pre {
    word-wrap: break-word;
    white-space: pre-wrap;
    word-break: normal;
}

.CodeMirror-linebackground {
    position: absolute;
    left: 0; right: 0; top: 0; bottom: 0;
    z-index: 0;
}

.CodeMirror-linewidget {
    position: relative;
    z-index: 2;
    overflow: auto;
}

.CodeMirror-widget {}

.CodeMirror-code {
    outline: none;
}

/* Force content-box sizing for the elements where we expect it */
.CodeMirror-scroll,
.CodeMirror-sizer,
.CodeMirror-gutter,
.CodeMirror-gutters,
.CodeMirror-linenumber {
    -moz-box-sizing: content-box;
    box-sizing: content-box;
}

.CodeMirror-measure {
    position: absolute;
    width: 100%;
    height: 0;
    overflow: hidden;
    visibility: hidden;
}

.CodeMirror-cursor {
    position: absolute;
    pointer-events: none;
}
.CodeMirror-measure pre { position: static; }

div.CodeMirror-cursors {
    visibility: hidden;
    position: relative;
    z-index: 3;
}
div.CodeMirror-dragcursors {
    visibility: visible;
}

.CodeMirror-focused div.CodeMirror-cursors {
    visibility: visible;
}

.CodeMirror-selected { background: #d9d9d9; }
.CodeMirror-focused .CodeMirror-selected { background: #d7d4f0; }
.CodeMirror-crosshair { cursor: crosshair; }
.CodeMirror-line::selection, .CodeMirror-line > span::selection, .CodeMirror-line > span > span::selection { background: #d7d4f0; }
.CodeMirror-line::-moz-selection, .CodeMirror-line > span::-moz-selection, .CodeMirror-line > span > span::-moz-selection { background: #d7d4f0; }

.cm-searching {
    background: #ffa;
    background: rgba(255, 255, 0, .4);
}

/* Used to force a border model for a node */
.cm-force-border { padding-right: .1px; }

@media print {
    /* Hide the cursor when printing */
    .CodeMirror div.CodeMirror-cursors {
        visibility: hidden;
    }
}

/* See issue #2901 */
.cm-tab-wrap-hack:after { content: ''; }

/* Help users use markselection to safely style text background */
span.CodeMirror-selectedtext { background: none; }

/*

    Name:       dracula
    Author:     Michael Kaminsky (http://github.com/mkaminsky11)

    Original dracula color scheme by Zeno Rocha (https://github.com/zenorocha/dracula-theme)

*/


.cm-s-dracula.CodeMirror, .cm-s-dracula .CodeMirror-gutters {
    background-color: #282a36 !important;
    color: #f8f8f2 !important;
    border: none;
}
.cm-s-dracula .CodeMirror-gutters { color: #282a36; }
.cm-s-dracula .CodeMirror-cursor { border-left: solid thin #f8f8f0; }
.cm-s-dracula .CodeMirror-linenumber { color: #6D8A88; }
.cm-s-dracula .CodeMirror-selected { background: rgba(255, 255, 255, 0.10); }
.cm-s-dracula .CodeMirror-line::selection, .cm-s-dracula .CodeMirror-line > span::selection, .cm-s-dracula .CodeMirror-line > span > span::selection { background: rgba(255, 255, 255, 0.10); }
.cm-s-dracula .CodeMirror-line::-moz-selection, .cm-s-dracula .CodeMirror-line > span::-moz-selection, .cm-s-dracula .CodeMirror-line > span > span::-moz-selection { background: rgba(255, 255, 255, 0.10); }
.cm-s-dracula span.cm-comment { color: #6272a4; }
.cm-s-dracula span.cm-string, .cm-s-dracula span.cm-string-2 { color: #f1fa8c; }
.cm-s-dracula span.cm-number { color: #bd93f9; }
.cm-s-dracula span.cm-variable { color: #50fa7b; }
.cm-s-dracula span.cm-variable-2 { color: white; }
.cm-s-dracula span.cm-def { color: #ffb86c; }
.cm-s-dracula span.cm-keyword { color: #ff79c6; }
.cm-s-dracula span.cm-operator { color: #ff79c6; }
.cm-s-dracula span.cm-keyword { color: #ff79c6; }
.cm-s-dracula span.cm-atom { color: #bd93f9; }
.cm-s-dracula span.cm-meta { color: #f8f8f2; }
.cm-s-dracula span.cm-tag { color: #ff79c6; }
.cm-s-dracula span.cm-attribute { color: #50fa7b; }
.cm-s-dracula span.cm-qualifier { color: #50fa7b; }
.cm-s-dracula span.cm-property { color: #66d9ef; }
.cm-s-dracula span.cm-builtin { color: #50fa7b; }
.cm-s-dracula span.cm-variable-3 { color: #50fa7b; }

.cm-s-dracula .CodeMirror-activeline-background { background: rgba(255,255,255,0.1); }
.cm-s-dracula .CodeMirror-matchingbracket { text-decoration: underline; color: white !important; }
    <?php
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}


function view_c474432f8da5706b55cded8c1409f237(array $data = []) {
    extract($data);
    ob_start(); ?>
    <?php
    $tm = $app->getThemeManager();
?>
<!DOCTYPE html>
<html>
    <head>
        <title>maki</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href='<?php echo $app->getResourceUrl($tm->getStylesheetPath($tm->getActiveStylesheet())) ?>' rel='stylesheet'>
        <script src="<?php echo $app->getResourceUrl('resources/jquery.js') ?>"></script>
    </head>
    <body class='login-page'>
    <div>
        <form>
            <div class="form-group">
                <input type="text" placeholder="Username">
            </div>
            <div class="form-group">
                <input type="password" placeholder="Password">
            </div>
            <div class="form-group checkbox">
                <label for="field-remember_me"><input type="checkbox" id="field-remember_me"> Remember me</label>
            </div>
            <div class="form-group">
                <button type="submit">login</button>
            </div>
        </form>
    </div>
    <script>
        $(function() {
            'use strict';

            var $form = $('form'),
                $name = $('input[type=text]'),
                $password = $('input[type=password]'),
                $remember = $('input[type=checkbox]');

            $form.on('submit', function(e) {
                e.preventDefault();

                $.ajax({
                    url: '?auth=1',
                    type: 'post',
                    data: {
                        username: $name.val(),
                        password: $password.val(),
                        remember: $remember[0].checked ? 1 : 0
                    },
                    success: function() {
                        window.location.reload();
                    },
                    error: function(xhr) {
                        $form.find('.username-form-error').remove();
                        $form.append('<p class="username-form-error">'+xhr.responseJSON.error+'</p>');
                    }
                });

                return false;
            });

        });
    </script>
    </body>
</html>
    <?php
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}


function view_4cc347ed786a2b22334b44fd54e1f5ba(array $data = []) {
    extract($data);
    ob_start(); ?>
    <?php
/**
 * @type \Maki\Maki $app
 * @type \Maki\File\Markdown $page
 * @type \Maki\File\Markdown $nav
 */
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="<?php echo $app->getResourceUrl($stylesheet) ?>" rel="stylesheet">
        <script src="<?php echo $app->getResourceUrl('resources/jquery.js') ?>"></script>
        <script src="<?php echo $app->getResourceUrl('resources/prism.js') ?>"></script>
        <script src="<?php echo $app->getResourceUrl('resources/toc.min.js') ?>"></script>
        <script>
            var __PAGE_PATH__ = '<?php echo $page->getFilePath() ?>';
        </script>
        <?php if ($editing): ?>
            <link href="<?php echo $app->getResourceUrl('resources/codemirror.css') ?>" rel='stylesheet'>
            <script src="<?php echo $app->getResourceUrl('resources/codemirror.js') ?>"></script>
            <script src="<?php echo $app->getResourceUrl('resources/codemirror-continuelist.js') ?>"></script>
            <script src="<?php echo $app->getResourceUrl('resources/codemirror-xml.js') ?>"></script>
            <script src="<?php echo $app->getResourceUrl('resources/codemirror-markdown.js') ?>"></script>
            <script src="<?php echo $app->getResourceUrl('resources/codemirror-rules.js') ?>"></script>
        <?php endif ?>
    </head>
    <body class="<?php echo $editing ? 'edit-mode' : '' ?>">
        <div class='container'>
            <header class="header">
                <h2><?php echo $app['main_title'] ?></h2>
                <?php if ($app['users']): ?>
                    <div class="user-actions">
                        hello <a><?php echo $app['user']['username'] ?></a> |
                        <a href="?logout=1">logout</a>
                    </div>
                <?php endif ?>
            </header>
            <div class='nav'>
                <div class='nav-inner'>
                    <?php echo $nav->toHTML() ?>
                    <?php if ($editable or $viewable): ?>
                        <div class='page-actions'>
                            <a href='<?php echo $nav->getUrl() ?>?edit=1' class='btn btn-xs btn-info pull-right'><?php echo $editButton ?></a>
                        </div>
                    <?php endif ?>
                </div>
            </div>
            <div class='content'>
                <ol class="breadcrumb">
                    <?php foreach ($page->getBreadcrumb() as $link): ?>
                        <li <?php echo $link['active'] ? 'class="active"' : '' ?>>
                            <?php if ($link['url']): ?>
                                <a href="<?php echo $link['url'] ?>"><?php echo $link['text'] ?></a>
                            <?php else: ?>
                                <?php echo $link['text'] ?>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ol>
                <div class='content-inner'>
                    <?php if ($editing): ?>
                        <div class='page-actions'>
                            <a href='<?php echo $page->getUrl() ?>' class='btn btn-xs btn-info'>back</a>
                            <?php if ($editable and $page->isNotLocked()): ?>
                                <a class='btn btn-xs btn-success save-btn'>save</a>
                                <span class='saved-info'>Document saved.</span>
                            <?php endif ?>

                            <?php if ($page->isLocked()): ?>
                                <span class='saved-info' style='display: inline-block'>Someone else is editing this document now.</span>
                            <?php endif ?>
                        </div>

                        <?php if ($editable and $page->isNotLocked()): ?>
                            <textarea id='textarea' class='textarea editor-textarea'><?php echo $page->getContent() ?></textarea>
                        <?php endif ?>
                    <?php else: ?>
                        <?php echo $page->toHTML() ?>

                        <?php if ($editable or $viewable): ?>
                            <div class='page-actions clearfix'>
                                <?php if ($editable): ?>
                                    <a href='<?php echo $page->getUrl() ?>?delete=1' data-confirm='Are you sure you want delete this page?' class='btn btn-xs btn-danger pull-right'>delete</a>
                                <?php endif ?>
                                <a href='<?php echo $page->getUrl() ?>?edit=1' class='btn btn-xs btn-info pull-right'><?php echo $editButton ?></a>
                            </div>
                        <?php endif ?>

                    <?php endif ?>
                </div>
            </div>
            <footer class='footer text-right'>
                <div class='themes'>
                    <select>
                        <?php foreach ($app->getThemeManager()->getStylesheets() as $name => $url): ?>
                            <option value='<?php echo $name ?>' <?php echo $name == $activeStylesheet ? 'selected="selected"' : '' ?>><?php echo $name ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <p class='copyrights'><a href='http://emve.org/maki' target='_blank' class='maki-name'><strong>ma</strong>ki</a> created by <a href='http://emve.org/' target='_blank' class='darkcinnamon-name'>emve</a></p>
            </footer>
        </div>
        <script>
            <?php if ($editing and $editable and $page->isNotLocked()): ?>
            var $saveBtns = $('.save-btn'),
                $saved = $('.saved-info'),
                editor;

            $saved.hide();

            function save() {
                $.ajax({
                    url: '<?php $page->getUrl() ?>?save=1',
                    method: 'post',
                    data: {
                        content:  editor.getValue()//$('#textarea').val()
                    },
                    success: function() {
                        $saveBtns.attr('disabled', 'disabled');
                        //$saved.show();
                        setTimeout(function() { save(); }, 5000);
                    }
                });
            };

            var editing = <?php echo var_export($editing, true) ?>;

            if (editing) {

                editor = CodeMirror.fromTextArea(document.getElementById("textarea"), {
                    mode: 'markdown',
                    tabSize: 4,
                    lineNumbers: false,
                    theme: "default",
                    extraKeys: {"Enter": "newlineAndIndentContinueMarkdownList"},
                    rulers: [{ color: '#ccc', column: 80, lineStyle: 'dashed' }]
                });


//                $('#textarea').on('keyup', function() {
//                    $saved.hide();
//                    $saveBtns.removeAttr('disabled');
//                });

                $(document).on('click', '.save-btn', save);

                save();
            }
            <?php endif ?>

            $(document).on('click', '[data-confirm]', function(e) {
                if (confirm($(this).attr('data-confirm'))) {
                    return true;
                } else {
                    e.preventDefault();
                    return false;
                }
            });

            var codeActionsTmpl = '' +
                '<div class="code-actions">' +
                '   <a href="#download" class="code-action-download">download</a>'
            '</div>';

            $('.content').find('pre > code').each(function(index) {
                var $this = $(this);

                if (this.className != '') {
                    this.className = 'language-'+this.className;
                }

                $(codeActionsTmpl)
                    .find('.code-action-download')
                    .attr('href', '?action=downloadCode&index=' + index)
                    .insertAfter($this.parent());
            });

            Prism.highlightAll();

            $('.themes > select').on('change', function() {
                window.location = '<?php $app->getCurrentUrl() ?>?change_css='+this.value;
            });

            $('.nav-inner [href="/'+__PAGE_PATH__+'"]').closest('li').append('<div id="page-toc"></div>');

            var toc = $('#page-toc');
            $('#page-toc').toc({
                container: '.content-inner'
            });

            if ($('>ul', toc).is(':empty')) {
                // Remove table of contents if it is empty
                // ----

                toc.remove();
            } else {
                // Scroll to nav toc
                $('.nav')[0].scrollTop = toc.position().top;

                // Remove h1 from toc
                toc.find('.toc-h1:first').remove();
            }
        </script>
    </body>
</html>
    <?php
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}


}
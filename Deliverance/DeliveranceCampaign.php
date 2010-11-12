<?php

require_once 'Swat/SwatString.php';
require_once 'Site/SiteLayoutData.php';
require_once 'Deliverance/DeliveranceList.php';

/**
 * @package   Deliverance
 * @copyright 2009-2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceCampaign
{
	// {{{ class constants

	/**
	 * Output formats
	 */
	const FORMAT_XHTML = 1;
	const FORMAT_TEXT  = 2;

	// }}}
	// {{{ public properties

	public $shortname;

	// }}}
	// {{{ protected properties

	protected $app;
	protected $directory;

	/**
	 * @var SiteLayoutData
	 */
	protected $data;

	protected $xhtml_template_filename = 'template-html.php';
	protected $text_template_filename  = 'template-text.php';

	// }}}
	// {{{ public function __construct()

	public function __construct(SiteApplication $app, $shortname, $directory)
	{
		$this->app       = $app;
		$this->shortname = $shortname;
		$this->directory = $directory;
		$this->data      = new SiteLayoutData();
	}

	// }}}
	// {{{ public function getAnalyticsKey()

	public function getAnalyticsKey()
	{
		$key = $this->shortname;

		return $key;
	}

	// }}}
	// {{{ public function getFromAddress()

	public function getFromAddress()
	{
		return null;
		// TODO: default to a config setting
	}

	// }}}
	// {{{ public function getFromName()

	public function getFromName()
	{
		return null;
	}

	// }}}
	// {{{ public function getTitle()

	public function getTitle()
	{
		return $this->shortname;
	}

	// }}}
	// {{{ public function getSubject()

	public function getSubject()
	{
		return null;
	}

	// }}}
	// {{{ public final function getContent()

	/**
	 * Gets the content of this mailing
	 *
	 * @param string $filename the filename of the template to use.
	 * @param integer $format integer contstant of the output format to use.
	 *
	 * @return string the content.
	 */
	public final function getContent($format = self::FORMAT_XHTML)
	{
		$filename = $this->getTemplateFilename($format);
		$this->build($format);

		ob_start();
		$this->data->display($filename);
		$content = ob_get_clean();
		$content = $this->replaceMarkers($content, $format);
		$content = $this->transform($content, $format);

		return $content;
	}

	// }}}
	// {{{ protected function build()

	/**
	 * Builds data properties before they are substituted into the layout
	 */
	protected function build($format)
	{
	}

	// }}}
	// {{{ protected function transform()

	protected function transform($content, $format) {
		switch ($format) {
		case self::FORMAT_XHTML:
			$document = $this->getDomDocument($content);
			$this->transformXhtml($document);
			$content = $document->saveXML(
				$document->documentElement, LIBXML_NOXMLDECL);

			break;

		case self::FORMAT_TEXT:
			$content = $this->transformText($content);
			break;
		}

		return $content;
	}

	// }}}
	// {{{ protected function transformXhtml()

	protected function transformXhtml($document)
	{
		$head_tags = $document->documentElement->getElementsByTagName('head');
		$head = $head_tags->item(0);

		// add meta Content-Type element to head for UTF-8 encoding
		$encoding = $document->createElement('meta');
		$encoding->setAttribute('http-equiv', 'Content-Type');
		$encoding->setAttribute('content', 'text/html; charset=utf-8');
		$head->insertBefore($encoding, $head->firstChild);

		// add base element to head
		$base = $document->createElement('base');
		$base->setAttribute('href', $this->getBaseHref());
		$head->insertBefore($base, $head->firstChild);

		// add analytics uri vars to all anchors in the rendered document
		$anchors = $document->documentElement->getElementsByTagName('a');
		foreach ($anchors as $anchor) {
			$href = $anchor->getAttribute('href');
			if (substr($href, 0, 2) != '*|') {
				$href = $this->appendAnalyticsToUri($href);
				$anchor->setAttribute('href', $href);
			}
		}
	}

	// }}}
	// {{{ protected function transformText()

	/**
	 * Mangles links to have ad tracking vars
	 */
	protected function transformText($text)
	{
		// prepend uris with base href
		$text = preg_replace('/:uri:(.*?)(\s)/',
			$this->getBaseHref().'\1\2', $text);

		if (mb_detect_encoding($text, 'UTF-8', true) !== 'UTF-8')
			throw new SiteException('Text output is not valid UTF-8');

		$text = SwatString::stripXHTMLTags($text);
		$text = html_entity_decode($text);

		return $text;
	}

	// }}}

	// {{{ protected function getBaseHref()

	protected function getBaseHref()
	{
		return $this->app->config->uri->absolute_base;
	}

	// }}}
	// {{{ protected function getResourceBaseHref()

	protected function getResourceBaseHref()
	{
		$base_href = $this->app->config->uri->absolute_resource_base;

		if ($base_href === null) {
			$base_href = $this->getBaseHref();
		}

		return $base_href;
	}

	// }}}
	// {{{ private function getDomDocument()

	private function getDomDocument($xhtml)
	{
		$internal_errors = libxml_use_internal_errors(true);

		$document = new DOMDocument();
		if (!$document->loadXML($xhtml)) {
			$xml_errors = libxml_get_errors();
			$message = '';
			foreach ($xml_errors as $error)
				$message.= sprintf("%s in %s, line %d\n",
					trim($error->message),
					$error->file,
					$error->line);

			libxml_clear_errors();
			libxml_use_internal_errors($internal_errors);

			$e = new Exception("Generated XHTML is not valid:\n".
				$message);

			throw $e;
		}

		libxml_use_internal_errors($internal_errors);

		return $document;
	}

	// }}}
	// {{{ protected function getCustomAnalyticsUriVars()

	protected function getCustomAnalyticsUriVars()
	{
		$vars = array();

		return $vars;
	}

	// }}}
	// {{{ protected function appendAnalyticsToUri()

	protected function appendAnalyticsToUri($uri)
	{
		$vars = array();

		foreach ($this->getCustomAnalyticsUriVars() as $name => $value)
			$vars[] = sprintf('%s=%s', urlencode($name), urlencode($value));

		if (count($vars)) {
			$var_string = implode('&', $vars);

			if (strpos($uri, '?') === false)
				$uri = $uri.'?'.$var_string;
			else
				$uri = $uri.'&'.$var_string;
		}

		return $uri;
	}

	// }}}
	// {{{ protected function getSourceDirectory()

	protected function getSourceDirectory()
	{
		return 'bogus';
	}

	// }}}
	// {{{ protected function getTemplateFilename()

	protected function getTemplateFilename($format)
	{
		$filename = $this->getSourceDirectory().'/';

		switch($format) {
		case DeliveranceCampaign::FORMAT_XHTML:
			$filename.= $this->xhtml_template_filename;
			break;

		case DeliveranceCampaign::FORMAT_TEXT:
			$filename.= $this->text_template_filename;
			break;
		}

		return $filename;
	}

	// }}}
	// {{{ protected function getReplacementMarkerText()

	/**
	 * Gets replacement text for a specfied replacement marker identifier
	 *
	 * @param string $marker_id the id of the marker found in the campaign
	 *                           content.
	 *
	 * @return string the replacement text for the given marker id.
	 */
	protected function getReplacementMarkerText($marker_id, $format)
	{
		// by default, always return a blank string as replacement text
		return '';
	}

	// }}}
	// {{{ protected final function replaceMarkers()

	/**
	 * Replaces markers in campaign with dynamic content
	 *
	 * @param string $text the content of the campaign.
	 * @param string $format the current format of the content.
	 *
	 * @return string the campaign content with markers replaced by dynamic
	 *                 content.
	 */
	protected final function replaceMarkers($text, $format)
	{
		$marker_pattern = '/<!-- \[(.*?)\] -->/u';

		$callback_function = ($format == self::FORMAT_XHTML) ?
			'getXhtmlReplacementMarkerTextByMatches' :
			'getTextReplacementMarkerTextByMatches';

		$callback = array($this, $callback_function);

		return preg_replace_callback($marker_pattern, $callback, $text);
	}

	// }}}
	// {{{ private final function getReplacementMarkerTextByMatches()

	private final function getXhtmlReplacementMarkerTextByMatches($matches)
	{
		return $this->getReplacementMarkerTextByMatches($matches,
			self::FORMAT_XHTML);
	}

	// }}}
	// {{{ private final function getTextReplacementMarkerTextByMatches()

	private final function getTextReplacementMarkerTextByMatches($matches)
	{
		return $this->getReplacementMarkerTextByMatches($matches,
			self::FORMAT_TEXT);
	}

	// }}}
	// {{{ private final function getReplacementMarkerTextByMatches()

	/**
	 * Gets replacement text for a replacement marker from within a matches
	 * array returned from a PERL regular expression
	 *
	 * @param array $matches the PERL regular expression matches array.
	 * @param string $format the current format of the content.
	 *
	 * @return string the replacement text for the first parenthesized
	 *                 subpattern of the <i>$matches</i> array.
	 */
	private final function getReplacementMarkerTextByMatches($matches, $format)
	{
		if (isset($matches[1]))
			return $this->getReplacementMarkerText($matches[1], $format);

		return '';
	}

	// }}}
}

?>
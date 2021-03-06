<?php
// vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4:
/**
 * BBCode: extension of base Text_Wiki text conversion handler
 *
 * PHP versions 4 and 5
 *
 * @category   Text
 * @package    Text_Wiki
 * @author     Bertrand Gugger <bertrand@toggg.com>
 * @copyright  2005 bertrand Gugger
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version    CVS: $Id: BBCode.php,v 1.14 2006/02/24 07:30:58 toggg Exp $
 * @link       http://pear.php.net/package/Text_Wiki
 */

/**
 * "master" class for handling the management and convenience
 */
@require_once 'Text/Wiki.php';

/**
 * Base Text_Wiki handler class extension for BBCode
 *
 * @category   Text
 * @package    Text_Wiki
 * @author     Bertrand Gugger <bertrand@toggg.com>
 * @copyright  2005 bertrand Gugger
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/Text_Wiki
 * @see        Text_Wiki::Text_Wiki()
 */
class Text_Wiki_CmsCode extends Text_Wiki {

    /**
     * The default list of rules, in order, to apply to the source text.
     *
     * @access public
     * @var array
     */
    var $rules = [
        'Prefilter',
//      'Delimiter',
//        'Code',
//        'Plugin',
//        'Function',
//        'Html',
        'Raw',
//        'Preformatted',
//        'Include',
//        'Embed',
//        'Page',
//        'Anchor',
//        'Heading',
//        'Toc',
//        'Titlebar',
//        'Horiz',
//        'Break',
//        'Blockquote',
//        'List',
//        'Deflist',
//        'Table',
//        'Box',
//        'Image',
//        'Smiley',
//        'Phplookup',
//        'Center',
        'Newline',
        'Paragraph',
        'Url',
//        'Freelink',
//        'Colortext',
//        'Font',
//        'Strong',
        'Bold',
//        'Emphasis',
        'Italic',
//        'Underline',
//        'Tt',
        'Superscript', // <sup> works, will need to check flash support
        'Subscript', // <sub> works, will need to check flash support
//        'Specialchar',
//        'Revise',
//        'Interwiki',
//        'Tighten'
    ];

    /**
     * Constructor: adds the path to CmsCode rules and adjusts the delimeter
     *
     * @access public
     * @param array $rules The set of rules to load for this object.
     */
    function __construct($rules = null)
    {
        parent::__construct($rules);

        # $this->delim defaults to "\xFF"; which is a bad idea since that is
        # small y diaresis
        $this->delim = "\x7F";
        $this->addPath('parse', $this->fixPath(dirname(__FILE__)) . 'CmsCode');
    }

    function Text_Wiki_CmsCode($rules = null)
    {
		self::__construct($rules);
    }
}

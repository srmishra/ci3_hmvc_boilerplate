<?php (defined('BASEPATH')) or exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 4.3.2 or newer
 *
 * @package CodeIgniter
 * @author  ExpressionEngine Dev Team
 * @copyright  Copyright (c) 2006, EllisLab, Inc.
 * @license http://codeigniter.com/user_guide/license.html
 * @link http://codeigniter.com
 * @since   Version 1.0
 * @filesource
 */
// --------------------------------------------------------------------

/**
 * CodeIgniter Template Class
 *
 * This class is and interface to CI's View class. It aims to improve the
 * interaction between controllers and views. Follow @link for more info
 *
 * @package		CodeIgniter
 * @author		Colin Williams
 * @subpackage	Libraries
 * @category	Libraries
 * @link		http://www.williamsconcepts.com/ci/libraries/template/index.html
 * @copyright  Copyright (c) 2008, Colin Williams.
 * @version 1.4.1
 * 
 */
class CI_Template {

    var $CI;
    var $config;
    var $template;
    var $master;
    var $regions = array(
        '_scripts' => array(),
        '_styles' => array(),
        '_meta' => array()
    );
    var $output;
    var $js = array();
    var $css = array();
    var $meta = array();
    
    /**
	 * List of cached variables
	 *
	 * @var array
	 * @access protected
	 */
	protected $_ci_cached_vars		= array();
    
    /**
	 * Nesting level of the output buffering mechanism
	 *
	 * @var int
	 * @access protected
	 */
	protected $_ci_ob_level;

    /**
     * Constructor
     *
     * Loads template configuration, template regions, and validates existence of 
     * default template
     *
     * @access	public
     */
    function CI_Template() {
        $this->_ci_ob_level  = ob_get_level();
		
        // Copy an instance of CI so we can use the entire framework.
        $this->CI = & get_instance();

        // Load the template config file and setup our master template and regions
        include(APPPATH . 'config/template' . EXT);
        if (isset($template)) {
            $this->config = $template;
            $this->set_template($template['active_template']);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Use given template settings
     *
     * @access  public
     * @param   string   array key to access template settings
     * @return  void
     */
    function set_template($group) {
        if (isset($this->config[$group])) {
            $this->template = $this->config[$group];
        } else {
            show_error('The "' . $group . '" template group does not exist. Provide a valid group name or add the group first.');
        }
        $this->initialize($this->template);
    }

    // --------------------------------------------------------------------

    // --------------------------------------------------------------------

    /**
     * Initialize class settings using config settings
     *
     * @access  public
     * @param   array   configuration array
     * @return  void
     */
    function initialize($props) {
        // Set master template
        if (isset($props['template'])
                && (file_exists(APPPATH . 'views/' . $props['template']) or file_exists(APPPATH . 'views/' . $props['template'] . EXT))) {
            $this->master = APPPATH . 'views/' . $props['template'] . EXT;
        } else {
            // Master template must exist. Throw error.
            show_error('Either you have not provided a master template or the one provided does not exist in <strong>' . APPPATH . 'views</strong>. Remember to include the extension if other than ".php"');
        }
    }
    
    // --------------------------------------------------------------------

    /**
     * Render the master template or a single region
     *
     * @access	public
     * @param	string	optionally opt to render a specific region
     * @param	boolean	FALSE to output the rendered template, TRUE to return as a string. Always TRUE when $region is supplied
     * @return	void or string (result of template build)
     */
    function render($_contents) {
        
        $_ci_vars = $this->_ci_object_to_array($_contents);
        
        if (is_array($_ci_vars))
		{
			$this->_ci_cached_vars = array_merge($this->_ci_cached_vars, $_ci_vars);
		}
		extract($this->_ci_cached_vars);

		ob_start();

		if ((bool) @ini_get('short_open_tag') === FALSE AND config_item('rewrite_short_tags') == TRUE)
		{
			echo eval('?>'.preg_replace("/;*\s*\?>/", "; ?>", str_replace('<?=', '<?php echo ', file_get_contents($this->master))));
		}
		else
		{
			include($this->master); // include() vs include_once() allows for multiple views with the same name
		}

		log_message('debug', 'File loaded: '.$this->master);

		
		if (ob_get_level() > $this->_ci_ob_level + 1)
		{
			ob_end_flush();
		}
		else
		{
			$this->CI->output->append_output(ob_get_contents());
			@ob_end_clean();
		}
    }
    
    // --------------------------------------------------------------------

	/**
	 * Object to Array
	 *
	 * Takes an object as input and converts the class variables to array key/vals
	 *
	 * @param	object
	 * @return	array
	 */
	protected function _ci_object_to_array($object)
	{
		return (is_object($object)) ? get_object_vars($object) : $object;
	}
    
    // --------------------------------------------------------------------

    /**
     * Dynamically include javascript in the template
     * 
     * NOTE: This function does NOT check for existence of .js file
     *
     * @access  public
     * @param   string   script to import or embed
     * @param   string  'import' to load external file or 'embed' to add as-is
     * @param   boolean  TRUE to use 'defer' attribute, FALSE to exclude it
     * @return  TRUE on success, FALSE otherwise
     */
    function add_js($script, $type = 'import', $defer = FALSE) {
        $success = TRUE;
        $js = NULL;

        $this->CI->load->helper('url');

        switch ($type) {
            case 'import':
                $filepath = base_url() . $script;
                $js = '<script type="text/javascript" src="' . $filepath . '"';
                if ($defer) {
                    $js .= ' defer="defer"';
                }
                $js .= "></script>";
                break;

            case 'embed':
                $js = '<script type="text/javascript"';
                if ($defer) {
                    $js .= ' defer="defer"';
                }
                $js .= ">";
                $js .= $script;
                $js .= '</script>';
                break;

            default:
                $success = FALSE;
                break;
        }

        // Add to js array if it doesn't already exist
        if ($js != NULL && !in_array($js, $this->js)) {
            $this->js[] = $js;
            $this->write('_scripts', $js);
        }

        return $success;
    }

    // --------------------------------------------------------------------

    /**
     * Dynamically include CSS in the template
     * 
     * NOTE: This function does NOT check for existence of .css file
     *
     * @access  public
     * @param   string   CSS file to link, import or embed
     * @param   string  'link', 'import' or 'embed'
     * @param   string  media attribute to use with 'link' type only, FALSE for none
     * @return  TRUE on success, FALSE otherwise
     */
    function add_css($style, $type = 'link', $media = FALSE) {
        $success = TRUE;
        $css = NULL;

        $this->CI->load->helper('url');
        $filepath = base_url() . $style;

        switch ($type) {
            case 'link':

                $css = '<link type="text/css" rel="stylesheet" href="' . $filepath . '"';
                if ($media) {
                    $css .= ' media="' . $media . '"';
                }
                $css .= ' />';
                break;

            case 'import':
                $css = '<style type="text/css">@import url(' . $filepath . ');</style>';
                break;

            case 'embed':
                $css = '<style type="text/css">';
                $css .= $style;
                $css .= '</style>';
                break;

            default:
                $success = FALSE;
                break;
        }

        // Add to js array if it doesn't already exist
        if ($css != NULL && !in_array($css, $this->css)) {
            $this->css[] = $css;
            $this->write('_styles', $css);
        }

        return $success;
    }

    // --------------------------------------------------------------------

    /**
     * Dynamically include meta tags in the template
     * 
     * @param string $key meta name
     * @param string $value meta content
     * @return bool
     */
    function add_meta($key, $val) {
        $success = FALSE;
        $meta = '<meta name="' . $key . '" content="' . $val . '" />';

        if (!in_array($meta, $this->meta)) {
            $this->meta[] = $meta;
            $this->write('_meta', $meta);
            $success = TRUE;
        }

        return $success;
    }

    // --------------------------------------------------------------------

}

// END Template Class

/* End of file Template.php */
/* Location: ./system/application/libraries/Template.php */
<?php
/**
* @version		$Id: advcomponent.php 8536 2007-08-23 18:14:11Z jinx $
* @package		Joomla
* @subpackage	JFramework
* @copyright	Copyright (C) 2005 - 2007 Open Source Matters. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* Joomla! is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die();

jimport('joomla.event.plugin');

/**
 * AdvComponent User Plugin
 *
 * @author		Enrico Valsecchi <admin@hostyle.it>
 * @package		Joomla
 * @subpackage	JFramework
 * @since 		1.5
 */
class plgSystemAdvComponent extends JPlugin {

	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for plugins
	 * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
	 * This causes problems with cross-referencing necessary for the observer design pattern.
	 *
	 * @param object $subject The object to observe
	 * @param 	array  $config  An array that holds the plugin configuration
	 * @since 1.5
	 */
	function plgSystemAdvComponent(&$subject) {
		parent::__construct($subject);
	}

	/**
	 * onAfterInizialise ()
	 *
	 * This function are called after system inizialization
	 * Search component in client request.
	 * If component does not exist, search in adv_component table
	 * where is a component to display in the main page and associated template
	 *
	 * If component are present in client request, search it in adv_component table
	 * and extract associated template
	 *
	 * At end, add _adv_component object into $mainframe
	 *
	 */
	function onAfterInitialise()
	{
		global $mainframe;
		// Action run ONLY if call arrive on public site!!!! :-)
		if ($mainframe->_name == 'site') 
		{
			// Get Database connection
			$db =& JFactory::getDBO();
			// Get user type
			$user =& JFactory::getUser();
			// Get a URI Object
			$objUri =& JURI::getInstance();
			$uri_query = $objUri->getQuery();
			$sef_enable = false;
			if( JPluginHelper::isEnabled('system', 'sef_advance') )
			{
				require_once( JPATH_SITE . DS . 'components' . DS . 'com_sef' . DS . 'sef_conf.php' );
				$sef_config = new SEF_AdvanceConfig();
				if( $sef_config->mosConfig_sef && $sef_config->sef_enabled )
				{	
					$sef_enable = true;
				}
			}
			// Set additional query to allow system get appropriate component and template
			// on different user type
			$add_query = ' AND #__adv_components.userlevel=0';
			if( $user->guest == 0 )
			{
				$add_query = ' AND #__adv_components.userlevel=1';
			}
			
			// In system are NOT available sef_advanced
			if( !$sef_enable )
			{
				// If $option is not set, get default component set on adv_components table
				if( !JRequest::getCmd('option') ) 
				{
					// Set a query to get default module and associated template
					$query = 'SELECT #__components.link, #__adv_components.component_id, #__adv_components.template_name, #__adv_components.sef_alias'
					.		 ' FROM #__adv_components'
					.		 ' LEFT JOIN #__components ON #__components.id=#__adv_components.component_id'
					.		 ' WHERE #__adv_components.master=1'
					.		 $add_query
					.		 ' AND #__adv_components.status=1'
					.		 ' AND #__components.parent=0'
					;
				
					// Set Query
					$db->setQuery( $query );
					// Load adv_component_object
					$adv_component = $db->loadObject();
					// If are a result from Db
					if( $adv_component )
					{
						// Set _uri with result of Db query
						$objUri->parse( $objUri->toString() . '?' . $adv_component->link );
						// Set option with component on JRequest object
						list ($act, $component) = explode ("=", $adv_component->link);
						JRequest::setVar('option', $component);
					}
				}
				else
				{
					// Set a query to get specified module and associated template
					$query = 'SELECT #__components.id, #__adv_components.component_id, #__adv_components.template_name, #__adv_components.sef_alias'
					.		 ' FROM #__adv_components'
					.		 ' LEFT JOIN #__components ON #__adv_components.component_id=#__components.id'
					.		 ' WHERE #__components.link=' . $db->Quote( 'option=' . JRequest::getCmd('option') )
					.		 $add_query
					.		 ' AND #__adv_components.status=1'
					.		 ' AND #__components.parent=0'
					;
					// Set Query on Db
					$db->setQuery( $query );
					// Load adv_component_object
					$adv_component = $db->loadObject();
				}
			}
			// O.K., system have a sef_advance enabled
			else
			{
				// Get last slashes occurrence in a string
				$last_slash_pos = strrpos( $objUri->current(), "/" );
				$page_request = substr( $objUri->current(), $last_slash_pos + 1 );
				if( !$page_request )
				{
					$where_query = " WHERE #__adv_components.master='1'";
				}
				else
				{
					$where_query = ' WHERE #__adv_components.sef_alias=' . $db->Quote( $page_request );
				}
				
				// Set a query to get specified module and associated template
				$query = 'SELECT #__components.id, #__adv_components.component_id, #__adv_components.template_name, #__adv_components.sef_alias'
				.		 ' FROM #__adv_components'
				.		 ' LEFT JOIN #__components ON #__adv_components.component_id=#__components.id'
				.		 $where_query
				.		 $add_query
				.		 ' AND #__adv_components.status=1'
				.		 ' AND #__components.parent=0'
				;
				// Set Query on Db
				$db->setQuery( $query );
				$adv_component = $db->loadObject();
				
				// O.K., requested query does not exists
				if( !$adv_component )
				{
					$mainframe->redirect( $objUri->root() );
					return;
				}
				$objUri->parse( $objUri->root() . $adv_component->sef_alias . '?' . $uri_query );
			}
			// Set adv_component object into mainframe
			$mainframe->_adv_component = $adv_component;
		}
	}
	
	/**
	 * onAfterRoute ()
	 *
	 * This function are called after system route
	 * If in $mainframe->_adv_component are defined a valid template,
	 * function save it in $mainframe->template_name
	 * With this step, for each module is possible assign specific template! :-)
	 */
	function onAfterRoute() {
		
		global $mainframe;
		
		// Action run ONLY if call arrive on public site!!!! :-)
		if ($mainframe->_name == 'site') 
		{
			// If $adv_component->template_name are not empty
			if (strlen( $mainframe->_adv_component->template_name ) > 0) 
			{
				// Set specific template page to component display
				$mainframe->setTemplate = $mainframe->_adv_component->template_name;
			}
		}
	}
}
?>

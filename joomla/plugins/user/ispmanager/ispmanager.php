<?php
/**
* @version		$Id: joomla.php 9069 2007-09-28 18:09:34Z jinx $
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

jimport('joomla.plugin.plugin');
/**
 * ISPManager User plugin
 *
 * @author		Enrico Valsecchi
 * @package		ISP Manager
 * @subpackage	ISP Manager Framework
 * @since 		1.6
 */
class plgUserISPManager extends JPlugin
{
	/**
	 * This method should handle any login logic and report back to the subject
	 *
	 * @access	public
	 * @param   array   holds the user data
	 * @param 	array   array holding options (remember, autoregister, group)
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function onUserLogin($user, $options = array())
	{
		// ISP Manager and Joomla! use two distinct DB for access and ISP Manager
		// get data of user from your DB. All of ISP Manager User are grouped under
		// 'Customer'. If user are into this group, routine follow and extract
		// all user data. Otherwise, this routine return true, without any modification
		//if( !JFactory::getApplication()->isAdmin() )
		//{
			jimport('joomla.user.helper');
			$instance = $this->_getUser($user, $options);

			// If _getUser returned an error, then pass it back.
			if (JError::isError($instance)) {
				return $instance;
			}

			// If the user is blocked, redirect with an error
			if( $instance->block == 1 )
			{
				return JError::raiseWarning('SOME_ERROR_CODE', JText::_('JERROR_NOLOGIN_BLOCKED'));
			}
			// Mark the user as logged in
			$instance->guest = 0;

			// Register the needed session variables
			$session = JFactory::getSession();
			$session->set('user', $instance);

			$db = JFactory::getDBO();

			// Check to see the the session already exists.
			$app = JFactory::getApplication();
			$app->checkSession();

			// Update the user related fields for the Joomla sessions table.
			$db->setQuery(
				'UPDATE `#__session`' .
				' SET `guest` = '.$db->quote($instance->get('guest')).',' .
				'	`username` = '.$db->quote($instance->get('username')).',' .
				'	`userid` = '.(int) $instance->get('id') .
				' WHERE `session_id` = '.$db->quote($session->getId())
			);
			$db->query();
		//}
		return true;
	}

	/**
	 * This method should handle any logout logic and report back to the subject
	 *
	 * @access public
	 * @param  array	holds the user data
	 * @param 	array   array holding options (client, ...)
	 * @return object   True on success
	 * @since 1.5
	 */
	public function onUserLogout($user, $options = array())
	{
		$my 		= JFactory::getUser();
		$session 	= JFactory::getSession();
		$app 		= JFactory::getApplication();

		// Make sure we're a valid user first
		if ($user['id'] == 0 && !$my->get('tmp_user')) {
			return true;
		}

		// Check to see if we're deleting the current session
		if ($my->get('id') == $user['id'] && $options['clientid'] == $app->getClientId()) {
			// Hit the user last visit field
			$my->setLastVisit();

			// Destroy the php session for this user
			$session->destroy();
		}
		
		// Force logout all users with that userid
		$db = JFactory::getDBO();
		$db->setQuery(
			'DELETE FROM `#__session`' .
			' WHERE `userid` = '.(int) $user['id'] .
			' AND `client_id` = '.(int) $options['clientid']
		);
		$db->query();

		return true;
	}
	
	/**
	 * This method will return a user object
	 *
	 * If options['autoregister'] is true, if the user doesn't exist yet he will be created
	 *
	 * @param	array	$user		Holds the user data.
	 * @param	array	$options	Array holding options (remember, autoregister, group).
	 *
	 * @return	object	A JUser object
	 * @since	1.5
	 */
	protected function &_getUser($user, $options = array())
	{
		$instance = JUser::getInstance();
		if ($id = intval(JUserHelper::getUserId($user['username'])))  {
			$instance->load($id);
			return $instance;
		}
		// If DB connection fails, return error.
		if( !$this->_db =& ISPMFactory::getDBO() )
		{
			return JError::raiseWarning('SOME_ERROR_CODE', JText::_('JGLOBAL_DB_CONNECTION_FAILED'));
		}
		// Get user detail from DB
		$query = 'SELECT * FROM #__user_identifying'
		.		 ' WHERE user_id=' . $this->_db->Quote($user['id'])
		;
		$this->_db->setQuery( $query );
		if( !$user_data = $this->_db->loadObject() )
		{
			return JError::raiseWarning('SOME_ERROR_CODE', JText::_('JGLOBAL_AUTH_INCORRECT'));
		}
		$instance->set('id'				, $user['id']);
		$instance->set('name'			, $user['fullname']);
		$instance->set('username'		, $user['username']);
		$instance->set('password_clear'	, $user['password_clear']);
		$instance->set('email'			, $user['email']);	// Result should contain an email (check)
		$instance->set('usertype'		, 'deprecated');
		$instance->set('groups'			, $user['groups']);

		// Set ISPM specific user data
		$user_data->user_id		= $user['id'];
		$user_data->group_id	= $user['group_id'];
		$user_data->group_name	= $user['group_name'];
		$user_data->status		= $user['status'];
		$user_data->ticket		= $user['ticket'];			

		// Get user credit
		$query = 'SELECT * FROM #__user_credit'
		.		 ' WHERE user_id=' . $this->_db->Quote($user_data->user_id)
		;
		$this->_db->setQuery( $query );
		$user_credit = $this->_db->loadAssoc();
		if( !$user_credit )
		{
			$user_credit['amount'] = 0.00;
		}
		$user_data->credit = $user_credit['amount'];
		
		$instance->set ( 'ispm_user', $user_data );
		return $instance;
	}
}
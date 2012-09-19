<?php
if (!defined('_PS_VERSION_'))
	exit;

class BanckleChat extends Module 
{                                                                            
	private $_html = '';
	private $_postErrors = array();
	private $url = "https://apps.banckle.com/api/"; 
                                                                            
	function __construct() 
	{
		$this->name = 'bancklechat';
		$this->tab = 'other';
		$this->version = '1.0';
		$this->author = 'Masood Anwer';
		parent::__construct();
		$this->displayName = $this->l('Banckle Chat');
		$this->description = $this->l('Through Banckle Chat Module for PrestaShop, get a step closer with your customers and save their time while assisting them all the way from adding items in the shopping cart to check-out process.');
	}
                                                                            
	public function install() 
	{
		parent::install();
		if(!$this->registerHook('LeftColumn')) return false;
		return true;
	}
	
	public function uninstall()
	{
	  if(!parent::uninstall()
		|| !Configuration::deleteByName('BANCKLE_CHAT_LOGINID') 
		|| !Configuration::deleteByName('BANCKLE_CHAT_PASSWORD')
		|| !Configuration::deleteByName('BANCKLE_CHAT_APPEARANCE')
		|| !Configuration::deleteByName('BANCKLE_CHAT_DEPLOYMENT'))   
		return false;
	  return true;
	} 
	
	private function _curlRequest($url, $method="GET", $postData="") 
	{
		$method = strtoupper($method);
		$headerType = "JSON";
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		
		if ($method == "GET") 	
			curl_setopt($ch, CURLOPT_HTTPGET, 1);
		else 
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json', 
		'Content-Length: '.strlen($postData))
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if (preg_match("/^(https)/i", $url)) 
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		
		$data = curl_exec($ch);
		curl_close($ch);

		return $data;
	}
	
	private function _authenticate() 
	{
		$userId  = Configuration::get('BANCKLE_CHAT_LOGINID');
		$password = Configuration::get('BANCKLE_CHAT_PASSWORD');
		$content = $this->_curlRequest($this->url . 'authenticate?userid=' . $userId . '&password=' . $password, "GET", "");
		if ($content !== false) 
			$arr = json_decode($content, true);
			//$arr = Tools::jsonDecode($content true);
			if (array_key_exists('error', $arr)) 
				return $arr['error']['details'];
			return $arr['return']['token'];
	}
	
	public function getContent() 
	{
		global $currentIndex;
		$this->_html = '';	
		$this->_html .= "<ul id='submenu' class='withLeftBorder clearfix'><li><a href=".$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&action=view>'.$this->l('Settings')."</a></li>&nbsp;&nbsp;";
		$this->_html .= "<li><a href=".$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&action=dashboard>'.$this->l('Dashboard')."</a></li></ul>";
		$this->_html .= "<br>";

		if ($this->_postValidation())
			$this->_postProcess();
		
		if (Tools::getValue('action') == "signin") 
			$this->_loginForm();
		if (Tools::getValue('action') == "signup")
			$this->_registerForm();
		if (Tools::getValue('action') == "view")
			$this->view();
		if (Tools::getValue('action') == "add")
			$this->_deploymentForm();
		if (Tools::getValue('action') == "edit")
			$this->_editForm();	
		if (Tools::getValue('action') == "dashboard")
			$this->dashboard();
				
		return $this->_html;
	}
	
	public function dashboard()
	{
		$this->_html .= '<div id="dashboarddiv"><iframe id="dashboardiframe" src="http://apps.banckle.com/livechat" height=800 width=98% scrolling="yes"></iframe></div><a href="http://apps.banckle.com/livechat" target="_newWindow" onClick="javascript:document.getElementById(\'dashboarddiv\').innerHTML=\'\'; ">Dashboard in a new window!</a>.';
	}
		
	private function _postProcess()
	{
		global $currentIndex;

		if (Tools::getValue('mod') == "login")
		{
			$content = $this->_curlRequest($this->url . "authenticate?userid=" . Tools::getValue('loginId') . "&password=" . Tools::getValue('password'), "GET", "");
			if ($content !== false) 
			{
				$arr = Tools::jsonDecode($content, true);
				if (array_key_exists('error', $arr)) 
				{
					$this->_html .= '<div class="error"><span style="float:right">
					<a id="hideError" href=""><img alt="X" src="../img/admin/close.png"></a></span>
					<img src="../img/admin/error2.png"><br>
					<ul><li>'.$this->l($arr['error']['details']).'</li></ul></div>';
				} else {
					Configuration::updateValue('BANCKLE_CHAT_LOGINID', Tools::getValue('loginId'));
					Configuration::updateValue('BANCKLE_CHAT_PASSWORD', Tools::getValue('password'));
					Tools::redirectAdmin($currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&action=view');
				}
			} 	
		}
		
		if (Tools::getValue('mod') == "register")
		{
			$content = $this->_curlRequest($this->url . 'registeruser?uid=' . Tools::getValue('loginId') . '&password=' . Tools::getValue('password') . '&email=' . Tools::getValue('email'), "POST", "");
			
			if ($content !== false) 
			{
				$arr = Tools::jsonDecode($content, true);
				if (!empty($arr) && array_key_exists('error', $arr)) 
				{
					$this->_html .= '<div class="error"><span style="float:right">
					<a id="hideError" href=""><img alt="X" src="../img/admin/close.png"></a></span>
					<img src="../img/admin/error2.png"><br>
					<ul><li>'.$this->l($arr['error']['details']).'</li></ul></div>';
				} else {
					$this->_html .= '<div class="conf"><img src="../img/admin/ok2.png" alt="">'.$this->l('Your account has been created. Please click Sign In Now link to login.').'</div>';	
				}
			}	
		}
		
		if (Tools::getValue('submit') == "Activate")
		{
			Configuration::updateValue('BANCKLE_CHAT_DEPLOYMENT', Tools::getValue('deployment'));
			$this->_html .= '<div class="conf"><img src="../img/admin/ok2.png" alt="">'.$this->l('Deployment is activated.').'</div>';	
		}
		
		if (Tools::getValue('submit') == "Customize")
		{
			Tools::redirectAdmin($currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&action=edit&deployment='.Tools::getValue('deployment'));
		}
		
		if (Tools::getValue('mod') == "add")
		{
			$department = Tools::getValue('department');
			$deployment = Tools::getValue('name');
			$title = Tools::getValue('title');			
			$copyright = Tools::getValue('copyright');
			$inviteMessage = Tools::getValue('inviteMessage');
			$welcomeMessage = Tools::getValue('welcomeMessage');
			$unavailableMessage = Tools::getValue('unavailableMessage');
			$waitingMessage = Tools::getValue('waitingMessage');
			$finalMessage = Tools::getValue('finalMessage');
			
			$appToken = $this->_authenticate();
			
			if ($this->_duplicateDeployment('add', $appToken, '') !== false) 
			{	
				$data = array(
							'name' => $deployment,
							'theme' => 'Theme-4', 
							'title' => $title, 
							'copyright' => $copyright,
							'departments' => $department,
							'inviteMessage' => $inviteMessage, 
							'welcomeMessage' => $welcomeMessage, 
							'unavailableMessage' => $unavailableMessage, 
							'waitingMessage' => $waitingMessage, 
							'finalMessage' => $finalMessage,
							'exitSurvey' => "",
							'enableAutoInvite' => 'false',
							'inviteTimeout' => '60',
							'autoInviteImage' => "",
							'enableProactiveInvite' => 'true',
							'enableInvitationFilter' => 'false',
							'invitationFilterType' => '0',
							'linkType' => '0',
							'themeFlags' => '0'
							);
				$postData = Tools::jsonEncode($data);				
					
				$content = $this->_curlRequest("https://apps.banckle.com/em/api/deployments.js?_token=".$appToken, "POST", $postData);
				
				Configuration::updateValue('BANCKLE_CHAT_APPEARANCE', Tools::getValue('appearance'));
				
				Tools::redirectAdmin($currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&action=view&success=Deployment has been created successfully.');
			} else {
				$this->_html .= '<div class="error"><span style="float:right"><a id="hideError" href=""><img alt="X" src="../img/admin/close.png"></a></span><img src="../img/admin/error2.png"><br>';
				$this->_html .= '<ul><li>'.$this->l('Deployment Name alreay exists. Please choose another name.').'</li></ul></div>';
			}
		}
		
		if (Tools::getValue('mod') == "edit")
		{
			$id = Tools::getValue('id');
			$appToken = Tools::getValue('appToken');
			$department = Tools::getValue('department');
			$deployment = Tools::getValue('name');
			$title = Tools::getValue('title');		
			$copyright = Tools::getValue('copyright');
			$inviteMessage = Tools::getValue('inviteMessage');
			$welcomeMessage = Tools::getValue('welcomeMessage');
			$unavailableMessage = Tools::getValue('unavailableMessage');
			$waitingMessage = Tools::getValue('waitingMessage');
			$finalMessage = Tools::getValue('finalMessage');
			
			if ($appToken && empty($appToken))
				$appToken = $this->_authenticate();
				
			if ($this->_duplicateDeployment('update', $appToken, $id) !== false) 
			{
				$data = array(
							'name' => $deployment,
							'theme' => 'Theme-4', 
							'title' => $title, 
							'copyright' => $copyright,
							'departments' => $department,
							'inviteMessage' => $inviteMessage, 
							'welcomeMessage' => $welcomeMessage, 
							'unavailableMessage' => $unavailableMessage, 
							'waitingMessage' => $waitingMessage, 
							'finalMessage' => $finalMessage,
							'exitSurvey' => '',
							'enableAutoInvite' => 'false',
							'inviteTimeout' => '60',
							'autoInviteImage' => '',
							'enableProactiveInvite' => 'true',
							'enableInvitationFilter' => 'false',
							'invitationFilterType' => '0',
							'linkType' => '0',
							'themeFlags' => '0'
						);
				$postData = Tools::jsonEncode($data);		
				//$content = $this->_curlRequest("https://apps.banckle.com/em/api/deployments/".$id.".js?_token=".$appToken."", "PUT", $postData);
				$content = $this->_curlRequest("https://chat.banckle.com/v2/deployments/".$id.".js?_token=".$appToken."", "PUT", $postData);
	
				Configuration::updateValue('BANCKLE_CHAT_APPEARANCE', Tools::getValue('appearance'));
				Tools::redirectAdmin($currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&action=view&success=Deployment has been updated successfully.');
			} else {
				$this->_html .= '<div class="error"><span style="float:right"><a id="hideError" href=""><img alt="X" src="../img/admin/close.png"></a></span><img src="../img/admin/error2.png"><br>';
				$this->_html .= '<ul><li>'.$this->l('Deployment Name alreay exists. Please choose another name.').'</li></ul></div>';
			}	
		}
		
	}	

	private function _postValidation()
	{
		if (Tools::isSubmit('submit'))
		{
			if (Tools::getValue('mod') == "login")
			{
				if (!Tools::getValue('loginId')) 
					$this->_postErrors[] = $this->l('Login is required.');	
					
				if (!Tools::getValue('password')) 
					$this->_postErrors[] = $this->l('Password is required.');
			}
			
			if (Tools::getValue('mod') == "register")
			{
				if (!Tools::getValue('email')) 
					$this->_postErrors[] = $this->l('Email is required.');
								
				if (!Tools::getValue('loginId')) 
					$this->_postErrors[] = $this->l('User Id is required.');		
					
				if (!Tools::getValue('password')) 
					$this->_postErrors[] = $this->l('Password is required.');
				
				if (!Tools::getValue('confirmPassword')) 
					$this->_postErrors[] = $this->l('Confirm Password is required.');
				
				if (Tools::getValue('password') && Tools::getValue('confirmPassword')) 
					if (Tools::getValue('password') != Tools::getValue('confirmPassword'))
						$this->_postErrors[] = $this->l('Password and Confirm Password do not match.');
			}
			
			if (Tools::getValue('mod') == "add" || Tools::getValue('mod') == "edit")
			{
				if (!Tools::getValue('name')) 
					$this->_postErrors[] = $this->l('Deployment Name is required.');	
					
				if (!Tools::getValue('title')) 
					$this->_postErrors[] = $this->l('Title is required.');				
			}
			
			if (sizeof($this->_postErrors))
			{
				$this->_html .= '<div class="error"><span style="float:right"><a id="hideError" href=""><img alt="X" src="../img/admin/close.png"></a></span><img src="../img/admin/error2.png"><br>';
				$this->_html .= '<ul>';
				foreach ($this->_postErrors AS $err)
					$this->_html .= '<li>'.$err.'</li>';
				$this->_html .= '</ul>';
				$this->_html .= '</div>';
				return false;
			} else {	
				return true;
			}
		}
	}
                                                                                                                                                      
	private function _loginForm() 
	{																																							
		global $currentIndex;
		
		$this->_html .= '<form action="'.$_SERVER['REQUEST_URI'].'" method="post">';
		$this->_html .= '<input type="hidden" name="mod" value="login">';
		$this->_html .= "<fieldset>";
		$this->_html .= "<legend>Banckle Sign In</legend>";
		$this->_html .= '<label>'.$this->l('Email: ').'</label>';
		$this->_html .= '<div class="margin-form">';
		$this->_html .= '<input type="text" name="loginId" size="30" value="'.Tools::getValue('loginId').'" >';
		$this->_html .= '</div>';
		$this->_html .= '<label>'.$this->l('Password: ').'</label>';
		$this->_html .= '<div class="margin-form">';
		$this->_html .= '<input type="password" name="password" size="30" >';
		$this->_html .= '</div>';
		$this->_html .= '<div class="margin-form">';
		$this->_html .= '<input type="submit" name="submit" ';
		$this->_html .= 'value="'.$this->l('Login').'" class="button" />';
		$this->_html .= '</div>';
		$this->_html .= '<div class="margin-form">';
		$this->_html .= 'Don\'t have a Banckle account? Please <a href="'.$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&action=signup" target="_blank">'.$this->l('Sign up').'</a>';
		$this->_html .= '</div>';
		$this->_html .= "</fieldset>";
		$this->_html .= '</form>';
	}
	
	
	private function _registerForm() 
	{																																							
		global $currentIndex;
		
		$this->_html .= '<form action="'.$_SERVER['REQUEST_URI'].'" method="post">';
		$this->_html .= '<input type="hidden" name="mod" value="register">';
		$this->_html .= "<fieldset>";
		$this->_html .= "<legend>Banckle Sign Up</legend>";
		$this->_html .= '<label>'.$this->l('Email: ').'</label>';
		$this->_html .= '<div class="margin-form">';
		$this->_html .= '<input type="text" name="email" size="30" value="'.Tools::getValue('email').'" >';
		$this->_html .= '</div>';
		$this->_html .= '<label>'.$this->l('User Id: ').'</label>';
		$this->_html .= '<div class="margin-form">';
		$this->_html .= '<input type="text" name="loginId" size="30" value="'.Tools::getValue('loginId').'" >';
		$this->_html .= '</div>';
		$this->_html .= '<label>'.$this->l('Password: ').'</label>';
		$this->_html .= '<div class="margin-form">';
		$this->_html .= '<input type="password" name="password" size="30" >';
		$this->_html .= '</div>';
		$this->_html .= '<label>'.$this->l('Confirm Password: ').'</label>';
		$this->_html .= '<div class="margin-form">';
		$this->_html .= '<input type="password" name="confirmPassword" size="30" >';
		$this->_html .= '</div>';
		$this->_html .= '<div class="margin-form">';
		$this->_html .= '<input type="submit" name="submit" ';
		$this->_html .= 'value="'.$this->l('Register').'" class="button" />';
		$this->_html .= '</div>';
		$this->_html .= '<div class="margin-form">';
		$this->_html .= 'Already have an account? <a href="'.$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&action=signin">'.$this->l('Sign In now!').'</a>';
		$this->_html .= '</div>';
		$this->_html .= "</fieldset>";
		$this->_html .= '</form>';
	}
		
	public function view()
	{
		global $currentIndex;
		
		if (!Configuration::get('BANCKLE_CHAT_LOGINID'))
			Tools::redirectAdmin($currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&action=signin');
		
		if (Tools::getValue('success'))
			$this->_html .= '<div class="conf"><img src="../img/admin/ok2.png" alt="">'.$this->l(Tools::getValue('success')).'</div>';	

		$this->_html .= '<a href='.$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&action=add><img src="../img/admin/add.gif">&nbsp;'.$this->l('Add New Deployment').'</a><br />';
		
		$activatedDeployment = Configuration::get('BANCKLE_CHAT_DEPLOYMENT');
		$appToken = $this->_authenticate();		
		$content = $this->_curlRequest("https://apps.banckle.com/em/api/deployments?_token=".$appToken."", "GET", "");
		
		if ($content !== false) 
		{
			$deployments = Tools::jsonDecode($content);		
			$this->_html .= '<form action="'.$_SERVER['REQUEST_URI'].'" method="post">';
			$this->_html .= "<fieldset><legend>Deployment</legend>"; 
			$this->_html .= '<label>'.$this->l('Deployment: ').'</label>';
			$this->_html .= '<div class="margin-form">';
			$select = "<select name='deployment'>";
			foreach($deployments as $deployment) 
			{
				if ($activatedDeployment == $deployment->id)
					$selected = "selected";
					
				$select .= "<option ".$selected." value='".$deployment->id."'>".$deployment->name."</option>";
				$selected = "";
			}
			$select .="</select>";
			$this->_html .= $select;
			$this->_html .= '</div>';
			$this->_html .= '<div class="margin-form">';
			$this->_html .= "<input type='submit' name='submit' value='Activate' class='button'>&nbsp;&nbsp;";
			$this->_html .= "<input type='submit' name='submit' value='Customize' class='button'>";
			
			$this->_html .= '</div>';
			$this->_html .= "</fieldset>";
			$this->_html .= "</form>";
		}	
	}
	
	private function _getDepartments($departmentId, $appToken) 
	{
		//if ($token && empty($token))
		if ($appToken == "")
			$appToken = $this->_authenticate();
			
		$content = $this->_curlRequest("https://apps.banckle.com/em/api/departments.js?_token=".$appToken."", "GET", "");
		if ($content !== false) 
		{
			//$result = json_decode($content);
			$result = Tools::jsonDecode($content);
			$selectList = "<select id='department' name='department'>";
			foreach ($result as $row) 
			{
				if ($departmentId == $row->id)
					$selected = "selected"; 

				$selectList .= "<option " . $selected ." value=" . $row->id . ">" . $row->displayName . "</option>";
				$selected = "";
			}
			$selectList .= "</select>";
			return $selectList;
		} 
	}
	
	private function _duplicateDeployment($action, $appToken, $id) 
	{
		$content = $this->_curlRequest("https://apps.banckle.com/em/api/deployments?_token=".$appToken."", "GET", "");
		if ($content !== false) 
		{
			$result = Tools::jsonDecode($content);
			foreach($result as $row) 
			{
				if ($action == 'update' && $id != $row->id && Tools::getValue('name') == $row->name) {
					return false;
				} else if ($action == 'add' && Tools::getValue('name') == $row->name) {
					return false;
				}
			}
		}
	}
	
	private function _deploymentForm() 
	{																																							
		global $currentIndex;
		
		if (Tools::getValue('copyright'))  
			$copyright = Tools::getValue('copyright');
		else
			$copyright = "Copyright is reserved by Banckle.";
		
		if (Tools::getValue('inviteMessage'))
			$inviteMessage = Tools::getValue('inviteMessage');
		else 
			$inviteMessage = "Do you have any questions?";
		
		if (Tools::getValue('welcomeMessage'))
			$welcomeMessage = Tools::getValue('welcomeMessage');
		else
			$welcomeMessage = "Welcome to Banckle";
		
		if (Tools::getValue('unavailableMessage'))
			$unavailableMessage = Tools::getValue('unavailableMessage');
		else
			$unavailableMessage = "Sorry, our service is unavailable now.";
		
		if (Tools::getValue('waitingMessage'))
			$waitingMessage = Tools::getValue('waitingMessage');
		else
			$waitingMessage = "Please stand by while we connect you to the next available operator...";	
		
		if (Tools::getValue('finalMessage'))
			$finalMessage = Tools::getValue('finalMessage');
		else
			$finalMessage = "Thank you for choosing Banckle.";
		
		if (Tools::getValue('appearance') == "banckleChatBottomLeft")
			$bottomLeft = "selected";
		
		if (Tools::getValue('appearance') == "banckleChatBottomRight")
			$bottomRight = "selected";
		
		if (Tools::getValue('appearance') == "banckleChatTopLeft")
			$topLeft = "selected";
		
		if (Tools::getValue('appearance') == "banckleChatTopRight")
			$topRight = "selected";
		
			
		$this->_html .= '
		<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
		<input type="hidden" name="mod" value="add">
		<fieldset>
		<legend>Add Deployment</legend>
		<label>'.$this->l('Name: ').'</label>
		<div class="margin-form">
		<input type="text" name="name" size="47" value="'.Tools::getValue('name').'" >&nbsp;<sup>*</sup>
		</div>
		<label>'.$this->l('Title: ').'</label>
		<div class="margin-form">
		<input type="text" name="title" size="47" value="'.Tools::getValue('title').'" >&nbsp;<sup>*</sup>
		</div>
		<label>'.$this->l('Copyright Text: ').'</label>
		<div class="margin-form">
		<input type="text" name="copyright" size="47" value="'.$copyright.'">
		</div>
		<label>'.$this->l('Department: ').'</label>
		<div class="margin-form">
		'.$this->_getDepartments($departments = "", $appToken = "").'
		</div>
		<label>'.$this->l('Invite: ').'</label>
		<div class="margin-form">
		<textarea name="inviteMessage" cols="50" rows="5">'.$inviteMessage.'</textarea>
		</div>
		<label>'.$this->l('Online: ').'</label>
		<div class="margin-form">
		<textarea name="welcomeMessage" cols="50" rows="5">'.$welcomeMessage.'</textarea>
		</div>
		<label>'.$this->l('Offline: ').'</label>
		<div class="margin-form">
		<textarea name="unavailableMessage" cols="50" rows="5">'.$unavailableMessage.'</textarea>
		</div>
		<label>'.$this->l('Waiting: ').'</label>
		<div class="margin-form">
		<textarea name="waitingMessage" cols="50" rows="5">'.$waitingMessage.'</textarea>
		</div>
		<label>'.$this->l('Exit: ').'</label>
		<div class="margin-form">
		<textarea name="finalMessage" cols="50" rows="5">'.$finalMessage.'</textarea>
		</div>
		<label>'.$this->l('Appearance:').'</label>
		<div class="margin-form">
		<select name="appearance">
			<option '.$bottomLeft.' value="banckleChatBottomLeft">Bottom Left</option>
			<option '.$bottomRight.' value="banckleChatBottomRight">Bottom Right</option>
			<option '.$topLeft.' value="banckleChatTopLeft">Top Left</option>
			<option '.$topRight.' value="banckleChatTopRight">Top Right</option>
		</select>
		</div>
		<div class="margin-form">
		<input type="submit" name="submit" value="'.$this->l('Add Deployment').'" class="button" />&nbsp;&nbsp;
		<a href='.$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&action=view>
		<input type="button" name="cancel" value="'.$this->l('Cancel').'" class="button"></a>
		</div>
		<div class="small"><sup>*</sup> Required field</div>
		</fieldset>
		</form>';
	}
	
	private function _editForm() 
	{																																							
		global $currentIndex;
		
		if (!Tools::getValue('mod')) 
		{
			$id = Tools::getValue('deployment');
			$appToken = $this->_authenticate();
			$content = $this->_curlRequest("https://apps.banckle.com/em/api/deployments/".$id."/?_token=".$appToken."", "GET", "");
			$result = Tools::jsonDecode($content);
		}

		if (Tools::getValue('appToken')) 
			$appToken = Tools::getValue('appToken');
		
		if (Tools::getValue('id')) 
			$id = Tools::getValue('id');
		
		if (Tools::getValue('name'))  
			$name = Tools::getValue('name');
		else
			$name = $result->name;
		
		if (Tools::getValue('title')) 
			$title = Tools::getValue('title');
		else
			$title = $result->title;
		
		if (Tools::getValue('copyright')) 
			$copyright = Tools::getValue('copyright');
		else
			$copyright = $result->copyright;
			if ($copyright == "")
				$copyright = "Copyright is reserved by Banckle.";
		
		if (Tools::getValue('departments')) 
			$departments = Tools::getValue('department');
		else
			$departments = $result->departments;
		
		if (Tools::getValue('inviteMessage')) 
			$inviteMessage = Tools::getValue('inviteMessage');
		else
			$inviteMessage = $result->inviteMessage;
			if ($inviteMessage == "")
				$inviteMessage = "Do you have any questions?";
		
		if (Tools::getValue('welcomeMessage'))
			$welcomeMessage = Tools::getValue('welcomeMessage');
		else
			$welcomeMessage = $result->welcomeMessage;
			if ($welcomeMessage == "")
				$welcomeMessage = "Welcome to Banckle";
		
		if (Tools::getValue('unavailableMessage')) 
			$unavailableMessage = Tools::getValue('unavailableMessage');
		else 
			$unavailableMessage = $result->unavailableMessage;
			if ($unavailableMessage == "")
				$unavailableMessage = "Sorry, our service is unavailable now.";
		
		if (Tools::getValue('waitingMessage')) 
			$waitingMessage = Tools::getValue('waitingMessage');
		else 
			$waitingMessage = $result->waitingMessage;
			if ($waitingMessage == "")
				$waitingMessage = "Please stand by while we connect you to the next available operator...";
		
		if (Tools::getValue('finalMessage'))
			$finalMessage = Tools::getValue('finalMessage');
		else
			$finalMessage = $result->finalMessage;
			if ($finalMessage == "")
				$finalMessage = "Thank you for choosing Banckle.";
		
		if (Tools::getValue('appearance'))
			$appearance = Tools::getValue('appearance'); 
		else	
			$appearance = Configuration::get('BANCKLE_CHAT_APPEARANCE');
		
		if ($appearance == "banckleChatBottomLeft")
			$banckleChatBottomLeft = 'selected';
		if ($appearance == "banckleChatBottomRight")
			$banckleChatBottomRight = 'selected';
		if ($appearance == "banckleChatTopLeft")
			$banckleChatTopLeft = 'selected';
		if ($appearance == "banckleChatTopRight")
			$banckleChatTopRight = 'selected';
						
		
		$this->_html .= '
		<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
		<input type="hidden" name="mod" value="edit">
		<input type="hidden" name="id" value="'.$id.'">
		<input type="hidden" name="appToken" value="'.$appToken.'">
		<fieldset>
		<legend>Edit Deployment</legend>
		<label>'.$this->l('Name: ').'</label>
		<div class="margin-form">
		<input type="text" name="name" size="47" value="'.$name.'" >&nbsp;<sup>*</sup>
		</div>
		<label>'.$this->l('Title: ').'</label>
		<div class="margin-form">
		<input type="text" name="title" size="47" value="'.$title.'" >&nbsp;<sup>*</sup>
		</div>
		<label>'.$this->l('Copyright Text: ').'</label>
		<div class="margin-form">
		<input type="text" name="copyright" size="47" value="'.$copyright.'" >
		</div>
		<label>'.$this->l('Department: ').'</label>
		<div class="margin-form">
		'.$this->_getDepartments($departments, $appToken).'
		</div>
		<label>'.$this->l('Invite: ').'</label>
		<div class="margin-form">
		<textarea name="inviteMessage" cols="50" rows="5">'.$inviteMessage.'</textarea>
		</div>
		<label>'.$this->l('Online: ').'</label>
		<div class="margin-form">
		<textarea name="welcomeMessage" cols="50" rows="5">'.$welcomeMessage.'</textarea>
		</div>
		<label>'.$this->l('Offline: ').'</label>
		<div class="margin-form">
		<textarea name="unavailableMessage" cols="50" rows="5">'.$unavailableMessage.'</textarea>
		</div>
		<label>'.$this->l('Waiting: ').'</label>
		<div class="margin-form">
		<textarea name="waitingMessage" cols="50" rows="5">'.$waitingMessage.'</textarea>
		</div>
		<label>'.$this->l('Exit: ').'</label>
		<div class="margin-form">
		<textarea name="finalMessage" cols="50" rows="5">'.$finalMessage.'</textarea>
		</div>
		<label>'.$this->l('Appearance: ').'</label>
		<div class="margin-form">
		<select name="appearance">
			<option value="banckleChatBottomLeft" '.$banckleChatBottomLeft.'>Bottom Left</option>
			<option value="banckleChatBottomRight" '.$banckleChatBottomRight.'>Bottom Right</option>
			<option value="banckleChatTopLeft" '.$banckleChatTopLeft.'>Top Left</option>
			<option value="banckleChatTopRight" '.$banckleChatTopRight.'>Top Right</option>
		</select>
		</div>
		<div class="margin-form">
		<input type="submit" name="submit" value="'.$this->l('Update Deployment').'" class="button" />&nbsp;&nbsp;
		<a href='.$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&action=view>
		<input type="button" name="cancel" value="'.$this->l('Cancel').'" class="button"></a>
		</div>
		<div class="small"><sup>*</sup> Required field</div>
		</fieldset>
		</form>';
	}


                                                                            
	public function hookLeftColumn() 
	{																			
		$appearance = Configuration::get('BANCKLE_CHAT_APPEARANCE');
		//if ($appearance && empty($appearance))
		if ($appearance == "")
			$appearance = "banckleChatBottomRight";
		$deployment = Configuration::get('BANCKLE_CHAT_DEPLOYMENT');
		Tools::addCSS(($this->_path).'button.css', 'all');
		
		if ($deployment && !empty($deployment))
		{
			global $smarty;
			$smarty->assign('deployment', $deployment);
			$smarty->assign('appearance', $appearance);
			return $this->display(__FILE__, 'bancklechat.tpl');  		
		}	
	}

	public function hookRightColumn($params) 
	{
		return $this->hookLeftColumn($params);
	}
                                                                            
} 
?>
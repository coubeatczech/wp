
<h1>Event Espresso API Settings</h1>
<p>The Event Espresso API allows machine-to-machine communication with your Event Espresso installation. This is required by the Event Espresso
iPad application and non-Wordpress programs. </p>
<h2>Options</h2>
<form method="post">
	<input type="hidden" name="<?php echo EspressoAPI_ADMIN_REAUTHENTICATE?>" value="true">
	<input type="submit" class='button' value="Force API clients to re-authenticate" ></input><br/>
	<p>By clicking the above button, all API sessions for anyone using the API (users of iphone app, iPad app, and other API clients)
		will be forced to provide their username and password again. Do this if you suspect an authenticated device (eg, iPad, computer, etc) has
	fallen into the hands of someone who shouldn't be allowed to access your private data</p>
</form>
<form method='post'>
	<label>API Session Timeout:</label>
	<select name="<?php echo EspressoAPI_ADMIN_SESSION_TIMEOUT?>">
		<?php foreach($templateVars[EspressoAPI_ADMIN_SESSION_TIMEOUT_OPTIONS] as $optionLabel=>$optionTime){
			$selectedHTML=$optionTime==$templateVars[EspressoAPI_ADMIN_SESSION_TIMEOUT]?'selected':'';?>
		<option value="<?php echo $optionTime?>" <?php echo $selectedHTML?>><?php echo $optionLabel?></option>
		<?php }?>
	</select>
	<p>Force API users to re-authenticate (login) at the stated time interval. Requiring users to login more frequently 
	may help improve security, but may also be tedious for API users.</p>
	<input type='submit' class='button'value='Save'>
</form>
<h2>Developers</h2> 
<p>For information on how to use the API, please read the <a href='http://codex.eventespresso.com/index.php?title=Rest_api'>Event Espresso Codex Documentation</a></p>

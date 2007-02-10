{extends template="swisdk.box"}

{assign var="title" value="Login"}

{block name="title"}
Login to {swisdk_runtime_value key="website.title"}
{/block}

{block name="content"}
<script type="text/javascript">
{literal}
//<![CDATA[
function focus_input()
{
	var user = document.getElementById('login_username');
	if(user.value!='')
		document.getElementById('login_password').focus();
	else
		user.focus();
}
add_event(window, 'load', focus_input);
//]]>
{/literal}
</script>

<form action="." method="post">
	<fieldset>

		<label for="login_username">Username</label>
		<input type="text" name="login_username" id="login_username" />

		<label for="login_password">Password</label>
		<input type="password" name="login_password" id="login_password" />

		<label for="login_private">Remember login</label>
		<input type="checkbox" name="login_private" id="login_private" />

		<input type="submit" value="Login" />
	</fieldset>
</form>
{/block}

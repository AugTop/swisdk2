{extends template=base.admin}

{block name=content}
<ul id="admin-modules">

{foreach from=$modules.pages item=module}
	<li><a href="{$module.url}"><img src="/media/admin/edit-copy.png" alt="" /> {$module.title}</a></li>
{/foreach}
</ul>
{/block}

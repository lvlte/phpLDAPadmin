<?php
// $Header$

/**
 * Classes and functions for the LDAP tree.
 *
 * @author The phpLDAPadmin development team
 * @package phpLDAPadmin
 */

/**
 * This class implements a straight HTML tree - no AJAX rendering is used.
 *
 * @package phpLDAPadmin
 * @subpackage Tree
 * @see AJAXTree Tree
 */
class HTMLTree extends Tree {

	protected $javascript = '';
	/**
	 * Required ABSTRACT methods
	 */
	/**
 	 * Displays the tree in HTML
	 *
	 * @param boolean Only display the tree, or include the server name and menu items
	 */
	public function draw($onlytree=false) {
		if (DEBUG_ENABLED)
			debug_log('Entered with ()',33,__FILE__,__LINE__,__METHOD__);

		$server = $this->getServer();

		echo '<table class="tree" border=0>';
		if (! $onlytree)
			$this->draw_server_name();

		$this->javascript = '';
		$javascript_id = 0;

		/* Do we have what it takes to authenticate here, or do we need to
		 * present the user with a login link (for 'cookie' and 'session' auth_types)?
		 */
		if ($server->isLoggedIn(null)) {
			if (! $onlytree) {
				$this->draw_menu();

				if ($server->getAuthType() != 'config')
					$this->draw_logged_in_dn();
				else
					printf('<tr><td class="blank" colspan="%s">&nbsp;</td></tr>',$this->getDepth()+3);

				if ($server->isReadOnly())
					printf('<tr><td class="spacer"></td><td class="logged_in" colspan="%s">(%s)</td></tr>',$this->getDepth()+3-1,_('read only'));
				else
					printf('<tr><td class="blank" colspan="%s">&nbsp;</td></tr>',$this->getDepth()+3);

				printf('<tr><td>&nbsp;</td><td><div style="overflow: auto; %s%s" id="ajSID_%s_nodes">',
					$_SESSION[APPCONFIG]->getValue('appearance','tree_width') ? sprintf('width: %spx; ',$_SESSION[APPCONFIG]->getValue('appearance','tree_width')) : '',
					$_SESSION[APPCONFIG]->getValue('appearance','tree_height') ? sprintf('height: %spx; ',$_SESSION[APPCONFIG]->getValue('appearance','tree_height')) : '',
					$server->getIndex());
			}

			echo '<table class="tree" border=0>';

			if (! count($this->getBaseEntries())) {
				# We didnt get any baseDN entries in our tree?
				printf('<tr><td class="spacer"></td><td class="spacer"></td><td colspan="%s"><small>%s<br />%s<br /><b>%s</b></small></td></tr>',
					$this->getDepth()+3-2,
					_('Could not determine the root of your LDAP tree.'),
					_('It appears that the LDAP server has been configured to not reveal its root.'),
					_('Please specify it in config.php'));

				echo '</table>';

				if (! $onlytree)
					echo '</div></td></tr>';

				echo '</table>';
				return;
			}

			/**
			 * Check if the LDAP server is not yet initialized
			 * (ie, the base DN configured in config.php does not exist)
			 */
			foreach ($this->getBaseEntries() as $base) {
				if (! $base->isInLDAP()) {
					$javascript_id++;

					printf('<tr><td class="spacer"></td><td class="spacer"></td><td><img src="%s/unknown.png" /></td><td colspan="%s">%s</td></tr>',
						IMGDIR,$this->getDepth()+3-3,pretty_print_dn($base->getDN()));

					$this->javascript .= sprintf('<form name="create_base_form_%s" method="post" action="cmd.php?cmd=template_engine">',$javascript_id);
					$this->javascript .= sprintf('<input type="hidden" name="server_id" value="%s" />',$server->getIndex());
					$this->javascript .= sprintf('<input type="hidden" name="container" value="%s" />',htmlspecialchars($server->getContainer($base->getDN())));
					$this->javascript .= sprintf('<input type="hidden" name="rdn" value="%s" />',get_rdn($base->getDN()));
					$this->javascript .= sprintf('</form>');

					printf('<tr><td class="spacer"></td><td class="spacer"></td><td class="spacer"></td><td colspan="%s"><small>%s<a href="javascript:document.create_base_form_%s.submit()">%s</a></small></td></tr>',
						$this->getDepth()+3-3,_('This base entry does not exist.'),$javascript_id,_('Create it?'));

					echo '</table>';

					if (! $onlytree)
						echo '</div></td></tr>';

					echo '</table>';
					return;

				} else {
					$this->draw_dn($base->getDN(),-1);
				}
			}

			echo '</table>';

			if (! $onlytree)
				echo '</div></td></tr>';

		# We are not logged in, draw a login... link.
		} else {
			if ($server->getAuthType() != 'config')
				$this->draw_login_link();
		}

		# Tree Footer.
		echo '</table>';
		echo "\n\n";

		static $js_drawn = false;

		if (! $js_drawn) {
			$this->draw_javascript();
			$js_drawn = true;
		}
	}

	/**
	 * Draw the server name
	 */
	protected function draw_server_name() {
		$server = $this->getServer();

		echo '<tr class="server">';
		printf('<td class="icon"><img src="%s/server.png" alt="%s" /></td>',IMGDIR,_('Server'));
		printf('<td class="name" colspan="%s">',$this->getDepth()+3-1);
		printf('%s',$server->getName());

		if (! is_null($server->inactivityTime())) {
			$m = sprintf(_('Inactivity will log you off at %s'),
				strftime('%H:%M',$server->inactivityTime()));
			printf(' <img width=14 height=14 src="%s/timeout.png" title="%s" alt="%s"/>',IMGDIR,$m,$m);
		}
		echo '</td></tr>';
	}

	/**
	 * Draw the tree menu options
	 */
	protected function draw_menu() {
		$links = '';
		$i = 0;

		foreach (array('schema','search','refresh','server_info','monitor','import','export','logout') as $menu) {
			$link = $this->get_menu_item($menu);
			if ($link)
				$links .= sprintf('<td class="server_links">%s</td>',$this->get_menu_item($menu));
		}

		# Draw the quick-links below the server name:
		if ($links) {
			printf('<tr><td class="spacer"></td><td colspan="%s" class="links">',$this->getDepth()+3-1);
			printf('<table><tr>%s</tr></table>',$links);
			echo '</td></tr>';
		}
	}

	/**
	 * Get the HTML for each tree menu option
	 */
	protected function get_menu_item($i) {
		$server = $this->getServer();
		$menu = array();

		switch($i) {
			case 'schema':
				if (! $_SESSION[APPCONFIG]->isCommandAvailable('script','schema'))
					return '';

				$menu['cmd'] = 'schema';
				$menu['ajax'] = _('Loading Schema');
				$menu['div'] = 'BODY';
				$menu['title'] = _('View schema for');
				$menu['img'] = 'schema.png';
				$menu['name'] = _('schema');
				break;

			case 'search':
				if (! $_SESSION[APPCONFIG]->isCommandAvailable('script','query_engine'))
					return '';

				$menu['cmd'] = 'query_engine';
				$menu['ajax'] = _('Loading Search');
				$menu['div'] = 'BODY';
				$menu['title'] = _('Search');
				$menu['img'] = 'search.png';
				$menu['name'] = _('search');
				break;

			case 'refresh':
				if (! $_SESSION[APPCONFIG]->isCommandAvailable('script','refresh'))
					return '';

				$menu['cmd'] = 'refresh';
				$menu['href'] = '&noheader=1&purge=1';
				$menu['ajax'] = _('Refreshing Tree');
				$menu['div'] = sprintf('SID_%s_nodes',$server->getIndex());
				$menu['title'] = _('Refresh');
				$menu['img'] = 'refresh-big.png';
				$menu['name'] = _('refresh');
				break;

			case 'server_info':
				if (! $_SESSION[APPCONFIG]->isCommandAvailable('script','server_info'))
					return '';

				$menu['cmd'] = 'server_info';
				$menu['ajax'] = _('Loading Info');
				$menu['div'] = 'BODY';
				$menu['title'] = _('Info');
				$menu['img'] = 'info.png';
				$menu['name'] = _('info');
				break;

			case 'monitor':
				if (! $_SESSION[APPCONFIG]->isCommandAvailable('script','monitor'))
					return '';

				# Fetch basic RootDSE attributes using the + and *.
				$query = array();
				$query['base'] = '';
				$query['scope'] = 'base';
				$query['attrs'] = $server->getValue('server','root_dse_attributes');
				$query['baseok'] = true;
				$results = $server->query($query,null);
				if ($results)
					$attrs = array_change_key_case(array_pop($results));
				else
					return '';

				if (! isset($attrs['monitorcontext']))
					return '';

				$menu['cmd'] = 'monitor';
				$menu['ajax'] = _('Loading Monitor Info');
				$menu['div'] = 'BODY';
				$menu['title'] = _('Monitor');
				$menu['img'] = 'ldap-server.png';
				$menu['name'] = _('monitor');
				break;

			case 'import':
				if (! $_SESSION[APPCONFIG]->isCommandAvailable('script','import') || $server->isReadOnly())
					return '';

				$menu['cmd'] = 'import_form';
				$menu['ajax'] = _('Loading Import');
				$menu['div'] = 'BODY';
				$menu['title'] = _('Import');
				$menu['img'] = 'import.png';
				$menu['name'] = _('import');
				break;

			case 'export':
				if (! $_SESSION[APPCONFIG]->isCommandAvailable('script','export'))
					return '';

				$menu['cmd'] = 'export_form';
				$menu['ajax'] = _('Loading Export');
				$menu['div'] = 'BODY';
				$menu['title'] = _('Export');
				$menu['img'] = 'export.png';
				$menu['name'] = _('export');
				break;

			case 'logout':
				if (in_array($server->getAuthType(),array('config','http')))
					return '';

				$href = sprintf('cmd.php?cmd=logout&server_id=%s',$server->getIndex());

				return sprintf('<a href="%s" title="%s"><img src="%s/%s" alt="%s" /><br />%s</a>',
					htmlspecialchars($href),_('Logout of this server'),IMGDIR,'logout.png',_('logout'),_('logout'));

			default:
				return false;
		}

		$href_parms = htmlspecialchars(sprintf('cmd=%s&server_id=%s%s',$menu['cmd'],$server->getIndex(),isset($menu['href']) ? $menu['href'] : ''));

		if (isAjaxEnabled())
			return sprintf('<a href="cmd.php?%s" onclick="return displayAJ(\'%s\',\'%s\',\'%s\');" title="%s %s"><img src="%s/%s" alt="%s" /><br />%s</a>',
				$href_parms,$menu['div'],$href_parms,$menu['ajax'],$menu['title'],$server->getName(),IMGDIR,$menu['img'],$menu['name'],$menu['name']);
		else
			return sprintf('<a href="cmd.php?%s" title="%s %s"><img src="%s/%s" alt="%s" /><br />%s</a>',
				$href_parms,$menu['title'],$server->getName(),IMGDIR,$menu['img'],$menu['name'],$menu['name']);
	}

	/**
	 * Draw the Logged in User DN
	 */
	protected function draw_logged_in_dn() {
		$server = $this->getServer();

		$logged_in_dn = $server->getLogin(null);
		echo '<tr>';
		echo '<td class="spacer"></td>';
		printf('<td class="logged_in" colspan="%s">%s: ',$this->getDepth()+3-1,_('Logged in as'));

		if ($server->getContainerTop($logged_in_dn) == $logged_in_dn) {
			$logged_in_branch = '';
			$logged_in_dn_array = array();

		} else {
			$logged_in_branch = preg_replace('/,'.$server->getContainerTop($logged_in_dn).'$/','',$logged_in_dn);
			$logged_in_dn_array = pla_explode_dn($logged_in_branch);
		}

		$bases = $server->getContainerTop($logged_in_dn);
		if (is_array($bases) && count($bases))
			array_push($logged_in_dn_array,$bases);

		$rdn = $logged_in_dn;

		# Some sanity checking here, in case our DN doesnt look like a DN
		if (! is_array($logged_in_dn_array))
			$logged_in_dn_array = array($logged_in_dn);

		if (trim($logged_in_dn)) {
			if ($server->dnExists($logged_in_dn))
				foreach ($logged_in_dn_array as $rdn_piece) {
					$href = sprintf('cmd.php?cmd=template_engine&server_id=%s&dn=%s',$server->getIndex(),rawurlencode($rdn));
					printf('<a href="%s">%s</a>',htmlspecialchars($href),pretty_print_dn($rdn_piece));

					if ($rdn_piece != end($logged_in_dn_array))
						echo ',';

					$rdn = substr($rdn,(1 + strpos($rdn,',')));
				}

			else
				echo $logged_in_dn;

		} else {
			echo 'Anonymous';
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Recursively descend on the given dn and draw the tree in html
	 *
	 * @param dn $dn Current dn.
	 * @param int $level Level to start drawing (start to -1)
	 * @todo This function hasnt been tested with the new rewrite of PLA
	 */
	protected function draw_dn($dn,$level) {
		if (DEBUG_ENABLED)
			debug_log('Entered with (%s,%s)',33,__FILE__,__LINE__,__METHOD__,$dn,$level);

		$server = $this->getServer();

		# Get entry to display as node
		$entry = $this->getEntry($dn);

		# If the entry doesnt exist, we'll add it.
		if (! $entry) {
			$this->addEntry($dn);
			$entry = $this->getEntry($dn);
		}

		# If the entry doesnt exist in the server, then return here with an empty string.
		if (! $entry)
			return;

		# Get our children.
		$child_count = $this->readChildrenNumber($dn);

		$rdn = get_rdn($dn);
		$dnENCODE = rawurlencode($dn);
		$href['expand'] = htmlspecialchars(sprintf('cmd.php?cmd=expand&server_id=%s&dn=%s',$server->getIndex(),$dnENCODE));
		$href['collapse'] = htmlspecialchars(sprintf('cmd.php?cmd=collapse&server_id=%s&dn=%s',$server->getIndex(),$dnENCODE));
		$href['edit'] = htmlspecialchars(sprintf('cmd.php?cmd=template_engine&server_id=%s&dn=%s',$server->getIndex(),$dnENCODE));

		echo '<tr class="option">';
		printf('<td class="spacer" colspan=%s></td>',$level+2);

		# Is this node expanded? (deciding whether to draw "+" or "-")
		if ($entry->isOpened())
			if (! $child_count && ! $this->getServer()->isShowCreateEnabled())
				printf('<td class="expander"><img src="%s/minus.png" alt="-" /></td>',IMGDIR);
			else
				printf('<td class="expander"><a href="%s"><img src="%s/minus.png" alt="-" /></a></td>',$href['collapse'],IMGDIR);
		else
			if (($child_count !== false) && (! $child_count) && (! $this->getServer()->isShowCreateEnabled()))
				printf('<td class="expander"><img src="%s/minus.png" alt="-" /></td>',IMGDIR);
			else
				printf('<td class="expander"><a href="%s"><img src="%s/plus.png" alt="+" /></a></td>',$href['expand'],IMGDIR);

		printf('<td class="icon"><a href="%s" id="node_%s_%s"><img src="%s/%s" alt="img" /></a></td>',
			$href['edit'],$server->getIndex(),preg_replace('/=/','_',base64_encode($dn)),IMGDIR,$entry->getIcon());

		printf('<td class="phplm" width=100%% colspan="%s"><span style="white-space: nowrap;">',$this->getDepth()+3-$level);
		printf('<a href="%s">%s</a>',$href['edit'],$this->get_formatted_dn($entry,$level));

		if ($child_count)
			printf(' <span class="count">(%s)</span>',$child_count);

		echo '</span></td></tr>';

		if ($entry->isOpened()) {
			/* Draw the "create new" link at the top of the tree list if there are more than 10
			 * entries in the listing for this node.
			 */
			if (!$server->isReadOnly() && (count($entry->getChildren()) > 10)
				&& $this->getServer()->isShowCreateEnabled()) {

				$this->draw_create_link($rdn,$level,$dnENCODE);
			}

			foreach ($entry->getChildren() as $dnChildEntry)
				$this->draw_dn($dnChildEntry,$level+1);

			# Always draw the "create new" link at the bottom of the listing
			if (! $server->isReadOnly() && ! $entry->isLeaf() && $this->getServer()->isShowCreateEnabled()) {
				$this->draw_create_link($rdn,$level,$dnENCODE);
			}
		}

		if (DEBUG_ENABLED)
			debug_log('Leaving (%s,%s)',33,__FILE__,__LINE__,__METHOD__,$dn,$level);
	}

	protected function get_formatted_dn($entry,$level) {
		if ($level < 0)
			return pretty_print_dn($entry->getDN());
		else
			return draw_formatted_dn($this->getServer(),$entry);
	}

	/**
	 * Print the HTML to show the "create new entry here".
	 *
	 * @param dn $rdn
	 * @param int $level
	 * @param dn $encoded_dn
	 */
	protected function draw_create_link($rdn,$level,$encoded_dn) {
		# print the "Create New object" link.
		$href = htmlspecialchars(sprintf('cmd.php?cmd=template_engine&server_id=%s&container=%s',$this->getServerID(),$encoded_dn));

		echo '<tr>';
		printf('<td class="spacer" colspan=%s></td>',$level+3);
		printf('<td class="icon"><a href="%s"><img src="%s/star.png" alt="%s" /></a></td>',$href,IMGDIR,_('new'));
		printf('<td class="link" colspan="%s"><a href="%s" title="%s %s">%s</a></td>',
			$this->getDepth()+3-$level,$href,_('Create a new entry in'),$rdn,_('Create new entry here'));
		echo '</tr>';
	}

	/**
	 * Draw login link
	 * @todo change the determination of $recently_timed_out_servers
	 */
	protected function draw_login_link() {
		global $recently_timed_out_servers;

		$server = $this->getServer();

		$href_parm = htmlspecialchars(sprintf('cmd=%s&server_id=%s',get_custom_file($server->getIndex(),'login_form',''),$server->getIndex()));

		echo '<tr class="option"><td class="spacer"></td>';

		if (isAjaxEnabled()) {
			printf('<td class="icon"><a href="cmd.php?%s" onclick="return displayAJ(\'BODY\',\'%s\',\'%s\');" title="%s %s"><img src="%s/%s" alt="%s" /></a></td>',
				$href_parm,$href_parm,_('Loading Login'),_('Login to'),$server->getName(),IMGDIR,'uid.png',_('login'));
			printf('<td class="logged_in" colspan="%s"><a href="cmd.php?%s" onclick="return displayAJ(\'BODY\',\'%s\',\'%s\');" title="%s %s">%s</a></td>',
				$this->getDepth()+3-2,$href_parm,$href_parm,_('Loading Login'),_('Login to'),$server->getName(),_('login'));

		} else {
			printf('<td class="icon"><a href="cmd.php?%s"><img src="%s/%s" alt="%s" /></a></td>',$href_parm,IMGDIR,'uid.png',_('login'));
			printf('<td class="logged_in" colspan="%s"><a href="cmd.php?%s">%s...</a></td>',$this->getDepth()+3-2,$href_parm,_('Login'));
		}

		echo '</tr>';

		printf('<tr><td class="blank" colspan="%s">&nbsp;</td>',$this->getDepth()+3);
		printf('<tr><td class="blank" colspan="%s">&nbsp;</td>',$this->getDepth()+3);

		# If the server recently timed out display the message
		if (is_array($recently_timed_out_servers) && in_array($server->getIndex(),$recently_timed_out_servers))
			printf('<tr><td class="spacer"></td><td colspan="%s" class="links">%s</td></tr>',
				$this->getDepth()+3-1,_('(Session timed out. Automatically logged out.)'));
	}

	/**
	 * Draw out link
	 */
	protected function draw_logout_link() {
		$server = $this->getServer();

		if (! in_array($server->getAuthType(),array('config','http')))
			printf('<tr><td class="spacer"></td><td colspan="%s"><small><a href="cmd.php?cmd=%s&server_id=%s">%s</a></small></td></tr>',
				$this->getDepth()+3-1,get_custom_file($server->getIndex(),'logout',''),$server->getIndex(),_('logout'));
	}

	/**
	 * If there is javascript, draw it
	 */
	protected function draw_javascript() {
		if ($this->javascript) {
			echo "<!-- Forms for javascript submit to call to create base_dns -->\n";
			echo $this->javascript;
			echo "<!-- The end of the forms for javascript submit to call to create base_dns -->\n";
		}
	}

	/*
	 * Work out how deep the "opened" tree is.
	 */
	public function getDepth() {
		$server = $this->getServer();

		# If we are not logged in
		if (! $server->isLoggedIn(null))
			return 0;

		static $depths = array();

		if (! isset($depths[$server->getIndex()])) {
			$max = 0; # BaseDN are open, so we start at 1.

			foreach ($this->entries as $dn) {
				$basedepth = count(pla_explode_dn($server->getContainerPath($dn->getDN(),'/')));
				$depth = 0;

				$depth = count(pla_explode_dn($dn->getDN()))+1-$basedepth;

				if ($depth > $max)
					$max = $depth;
			}

			$depths[$server->getIndex()] = $max;
		}

		return $depths[$server->getIndex()];
	}
}
?>
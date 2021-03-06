<?php
/**
 * Menus
 *
 * @package   MyAAC
 * @author    Slawkens <slawkens@gmail.com>
 * @copyright 2019 MyAAC
 * @link      https://my-aac.org
 */
defined('MYAAC') or die('Direct access not allowed!');
$title = 'Menus';

if (!hasFlag(FLAG_CONTENT_MENUS) && !superAdmin()) {
	echo 'Access denied.';
	return;
}

if (isset($_REQUEST['template'])) {
	$template = $_REQUEST['template'];

	if (isset($_REQUEST['menu'])) {
		$post_menu = $_REQUEST['menu'];
		$post_menu_link = $_REQUEST['menu_link'];
		$post_menu_blank = $_REQUEST['menu_blank'];
		$post_menu_color = $_REQUEST['menu_color'];
		if (count($post_menu) != count($post_menu_link)) {
			echo 'Menu count is not equal menu links. Something went wrong when sending form.';
			return;
		}

		$db->query('DELETE FROM `' . TABLE_PREFIX . 'menu` WHERE `template` = ' . $db->quote($template));
		foreach ($post_menu as $category => $menus) {
			foreach ($menus as $i => $menu) {
				if (empty($menu)) // don't save empty menu item
					continue;

				try {
					$db->insert(TABLE_PREFIX . 'menu', array('template' => $template, 'name' => $menu, 'link' => $post_menu_link[$category][$i], 'blank' => $post_menu_blank[$category][$i] == 'on' ? 1 : 0, 'color' => str_replace('#', '', $post_menu_color[$category][$i]), 'category' => $category, 'ordering' => $i));
				} catch (PDOException $error) {
					warning('Error while adding menu item (' . $menu . '): ' . $error->getMessage());
				}
			}
		}

		$cache = Cache::getInstance();
		if ($cache->enabled()) {
			$cache->delete('template_menus');
		}
		success('Saved at ' . date('H:i'));
	}

	$file = TEMPLATES . $template . '/config.php';
	if (file_exists($file)) {
		require_once $file;
	} else {
		echo 'Cannot find template config.php file.';
		return;
	}
	if (!isset($config['menu_categories'])) {
		echo "No menu categories set in template config.php.<br/>This template doesn't support dynamic menus.";
		return;
	}
	?>
	<div align="center" class="text-center">
		<p class="note">Hint: You can drag menu items.<br/>
			Hint: Add links to external sites using: <b>http://</b> or <b>https://</b> prefix.<br/>
			Not all templates support blank and colorful links.
		</p>
	</div>
	<?php
	$menus = array();
	$menus_db = $db->query('SELECT `name`, `link`, `blank`, `color`, `category`, `ordering` FROM `' . TABLE_PREFIX . 'menu` WHERE `enabled` = 1 AND `template` = ' . $db->quote($template) . ' ORDER BY `ordering` ASC;')->fetchAll();
	foreach ($menus_db as $menu) {
		$menus[$menu['category']][] = array('name' => $menu['name'], 'link' => $menu['link'], 'blank' => $menu['blank'], 'color' => $menu['color'], 'ordering' => $menu['ordering']);
	}
	$last_id = array();
	?>
	<form method="post" id="menus-form" action="?p=menus">
		<input type="hidden" name="template" value="<?php echo $template ?>"/>
		<div class="row">
			<?php foreach ($config['menu_categories'] as $id => $cat): ?>
				<div class="col-md-12 col-lg-6">
					<div class="card card-info card-outline">
						<div class="card-header">
							<h5 class="m-0"><?php echo $cat['name'] ?> <i class="far fa-plus-square add-button" id="add-button-<?php echo $id ?>"></i></h5>
						</div>
						<div class="card-body">
							<ul class="sortable" id="sortable-<?php echo $id ?>">
								<?php
								if (isset($menus[$id])) {
									foreach ($menus[$id] as $i => $menu):
										?>
										<li class="ui-state-default" id="list-<?php echo $id ?>-<?php echo $i ?>"><label>Name:</label> <input type="text" name="menu[<?php echo $id ?>][]" value="<?php echo $menu['name'] ?>"/>
											<label>Link:</label> <input type="text" name="menu_link[<?php echo $id ?>][]" value="<?php echo $menu['link'] ?>"/>
											<input type="hidden" name="menu_blank[<?php echo $id ?>][]" value="0"/>
											<label><input class="blank-checkbox" type="checkbox" <?php echo($menu['blank'] == 1 ? 'checked' : '') ?>/><span title="Open in New Window">New Window</span></label>
											<input class="color-picker" type="text" name="menu_color[<?php echo $id ?>][]" value="#<?php echo $menu['color'] ?>"/>
											<a class="remove-button" id="remove-button-<?php echo $id ?>-<?php echo $i ?>"><i class="fas fa-trash"></a></i></li>
										<?php $last_id[$id] = $i;
									endforeach;
								} ?>
							</ul>
						</div>
					</div>
				</div>
			<?php endforeach ?>
		</div>
		<div class="row pb-2">
			<div class="col-md-12">
				<button type="submit" class="btn btn-info"><i class="fas fa-update"></i> Save</button>
				<?php
				echo '<button type="button" class="btn btn-danger float-right" value="Cancel" onclick="window.location = \'' . ADMIN_URL . '?p=menus&template=' . $template . '\';"><i class="fas fa-cancel"></i> Cancel</button>';
				?>
			</div>
		</div>
	</form>
	<?php
	$twig->display('admin.menus.js.html.twig', array(
		'menus' => $menus,
		'last_id' => $last_id
	));
	?>
	<?php
} else {
	$templates = $db->query('SELECT `template` FROM `' . TABLE_PREFIX . 'menu` GROUP BY `template`;')->fetchAll();
	foreach ($templates as $key => $value) {
		$file = TEMPLATES . $value['template'] . '/config.php';
		if (!file_exists($file)) {
			unset($templates[$key]);
		}
	}

	$twig->display('admin.menus.form.html.twig', array(
		'templates' => $templates
	));
}

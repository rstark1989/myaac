<?php
/**
 * Account editor
 *
 * @package   MyAAC
 * @author    Lee
 * @copyright 2020 MyAAC
 * @link      https://my-aac.org
 */
defined('MYAAC') or die('Direct access not allowed!');

$title = 'Account editor';
$admin_base = BASE_URL . 'admin/?p=accounts';

if ($config['account_country'])
	require SYSTEM . 'countries.conf.php';

$hasSecretColumn = $db->hasColumn('accounts', 'secret');
$hasCoinsColumn = $db->hasColumn('accounts', 'coins');
$hasPointsColumn = $db->hasColumn('accounts', 'premium_points');
$hasTypeColumn = $db->hasColumn('accounts', 'type');
$hasGroupColumn = $db->hasColumn('accounts', 'group_id');

if ($config['account_country']) {
	$countries = array();
	foreach (array('pl', 'se', 'br', 'us', 'gb') as $c)
		$countries[$c] = $config['countries'][$c];

	$countries['--'] = '----------';
	foreach ($config['countries'] as $code => $c)
		$countries[$code] = $c;
}
$web_acc = array("None", "Admin", "Super Admin", "(Admin + Super Admin)");
$acc_type = array("None", "Normal", "Tutor", "Senior Tutor", "Gamemaster", "God");
?>

<link rel="stylesheet" type="text/css" href="<?php echo BASE_URL; ?>tools/css/jquery.datetimepicker.css"/ >
<script src="<?php echo BASE_URL; ?>tools/js/jquery.datetimepicker.js"></script>

<?php
$id = 0;
$search_account = '';
if (isset($_REQUEST['id']))
	$id = (int)$_REQUEST['id'];
else if (isset($_REQUEST['search'])) {
	$search_account = $_REQUEST['search'];
	if (strlen($search_account) < 3 && !Validator::number($search_account)) {
		echo_error('Player name is too short.');
	} else {
		$query = $db->query('SELECT `id` FROM `accounts` WHERE `name` = ' . $db->quote($search_account));
		if ($query->rowCount() == 1) {
			$query = $query->fetch();
			$id = (int)$query['id'];
		} else {
			$query = $db->query('SELECT `id`, `name` FROM `accounts` WHERE `name` LIKE ' . $db->quote('%' . $search_account . '%'));
			if ($query->rowCount() > 0 && $query->rowCount() <= 10) {
				$str_construct = 'Do you mean?<ul class="mb-0">';
				foreach ($query as $row)
					$str_construct .= '<li><a href="' . $admin_base . '&id=' . $row['id'] . '">' . $row['name'] . '</a></li>';
				$str_construct .= '</ul>';
				echo_error($str_construct);
			} else if ($query->rowCount() > 10)
				echo_error('Specified name resulted with too many accounts.');
			else
				echo_error('No entries found.');
		}
	}
}
?>
<div class="row">
	<?php
	$groups = new OTS_Groups_List();
	if ($id > 0) {
		$account = new OTS_Account();
		$account->load($id);

		if (isset($account, $_POST['save']) && $account->isLoaded()) {
			$error = false;

			$_error = '';
			$account_db = new OTS_Account();
			if (USE_ACCOUNT_NAME) {
				$name = $_POST['name'];

				$account_db->find($name);
				if ($account_db->isLoaded() && $account->getName() != $name)
					echo_error('This name is already used. Please choose another name!');
			}

			$account_db->load($id);
			if (!$account_db->isLoaded())
				echo_error('Account with this id doesn\'t exist.');

			//type/group
			if ($hasTypeColumn || $hasGroupColumn) {
				$group = $_POST['group'];
			}

			$password = ((!empty($_POST["pass"]) ? $_POST['pass'] : null));
			if (!Validator::password($password)) {
				$errors['password'] = Validator::getLastError();
			}

			//secret
			if ($hasSecretColumn) {
				$secret = $_POST['secret'];
			}

			//key
			$key = $_POST['key'];
			$email = $_POST['email'];
			if (!Validator::email($email))
				$errors['email'] = Validator::getLastError();

			//tibia coins
			if ($hasCoinsColumn) {
				$t_coins = $_POST['t_coins'];
				verify_number($t_coins, 'Tibia coins', 12);
			}
			// prem days
			$p_days = (int)$_POST['p_days'];
			verify_number($p_days, 'Prem days', 11);

			//prem points
			$p_points = $_POST['p_points'];
			verify_number($p_points, 'Prem Points', 11);

			//rl name
			$rl_name = $_POST['rl_name'];

			//location
			$rl_loca = $_POST['rl_loca'];

			//country
			$rl_country = $_POST['rl_country'];

			$web_flags = $_POST['web_flags'];
			verify_number($web_flags, 'Web Flags', 1);

			//created
			$created = strtotime($_POST['created']);
			verify_number($created, 'Created', 11);

			//web last login
			$web_lastlogin = strtotime($_POST['web_lastlogin']);
			verify_number($web_lastlogin, 'Web Last login', 11);

			if (!$error) {
				if (USE_ACCOUNT_NAME) {
					$account->setName($name);
				}

				if ($hasTypeColumn) {
					$account->setCustomField('type', $group);
				} elseif ($hasGroupColumn) {
					$account->setCustomField('group_id', $group);
				}

				if ($hasSecretColumn) {
					$account->setCustomField('secret', $secret);
				}
				$account->setCustomField('key', $key);
				$account->setEMail($email);
				if ($hasCoinsColumn) {
					$account->setCustomField('coins', $t_coins);
				}

				$lastDay = 0;
				if ($p_days != 0 && $p_days != PHP_INT_MAX) {
					$lastDay = time();
				} else if ($lastDay != 0) {
					$lastDay = 0;
				}

				$account->setPremDays($p_days);
				$account->setLastLogin($lastDay);
				if ($hasPointsColumn) {
					$account->setCustomField('premium_points', $p_points);
				}
				$account->setRLName($rl_name);
				$account->setLocation($rl_loca);
				$account->setCountry($rl_country);
				$account->setCustomField('created', $created);
				$account->setWebFlags($web_flags);
				$account->setCustomField('web_lastlogin', $web_lastlogin);

				if (isset($password)) {
					$config_salt_enabled = $db->hasColumn('accounts', 'salt');
					if ($config_salt_enabled) {
						$salt = generateRandomString(10, false, true, true);
						$password = $salt . $password;
						$account->setCustomField('salt', $salt);
					}

					$password = encrypt($password);
					$account->setPassword($password);

					if ($config_salt_enabled)
						$account->setCustomField('salt', $salt);
				}

				$account->save();
				echo_success('Account saved at: ' . date('G:i'));
			}
		}
	} else if ($id == 0) {
		$accounts_db = $db->query('SELECT `id`, `name`,' . ($hasTypeColumn ? 'type' : 'group_id') . ' FROM `accounts` ORDER BY `id` ASC');
		?>
		<div class="col-12 col-sm-12 col-lg-10">
			<div class="card card-info card-outline">
				<div class="card-header">
					<h5 class="m-0">Accounts</h5>
				</div>
				<div class="card-body">
					<table class="acc_datatable table table-striped table-bordered">
						<thead>
						<tr>
							<th>ID</th>
							<th>Name</th>
							<th>Position</th>
							<th style="width: 40px">Edit</th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ($accounts_db as $account_lst): ?>
							<tr>
								<th><?php echo $account_lst['id']; ?></th>
								<td><?php echo $account_lst['name']; ?></a></td>
								<td>
									<?php if ($hasTypeColumn) {
										echo $acc_type[$account_lst['type']];
									} elseif ($hasGroupColumn) {
										$group = $groups->getGroups();
										echo $group[$account_lst['group_id']];
									} ?>
								</td>
								<td><a href="?p=accounts&id=<?php echo $account_lst['id']; ?>" class="btn btn-success btn-sm" title="Edit">
										<i class="fas fa-pencil-alt"></i>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	<?php } ?>

	<?php if (isset($account) && $account->isLoaded()) { ?>
		<div class="col-12 col-sm-12 col-lg-10">
			<div class="card card-primary card-outline card-outline-tabs">
				<div class="card-header p-0 border-bottom-0">
					<ul class="nav nav-tabs" id="accounts-tab" role="tablist">
						<li class="nav-item">
							<a class="nav-link active" id="accounts-acc-tab" data-toggle="pill" href="#accounts-acc">Account</a>
						</li>
						<li class="nav-item">
							<a class="nav-link" id="accounts-chars-tab" data-toggle="pill" href="#accounts-chars">Characters</a>
						</li>
						<?php if ($db->hasTable('bans')) : ?>
							<li class="nav-item">
								<a class="nav-link" id="accounts-bans-tab" data-toggle="pill" href="#accounts-bans">Bans</a>
							</li>
						<?php endif;

						if ($db->hasTable('store_history')) : ?>
							<li class="nav-item">
								<a class="nav-link" id="accounts-store-tab" data-toggle="pill" href="#accounts-store">Store History</a>
							</li>
						<?php endif; ?>
					</ul>
				</div>
				<div class="card-body">
					<div class="tab-content" id="accounts-tabContent">
						<div class="tab-pane fade active show" id="accounts-acc">
							<form action="<?php echo $admin_base . ((isset($id) && $id > 0) ? '&id=' . $id : ''); ?>" method="post">
								<div class="form-group row">
									<?php if (USE_ACCOUNT_NAME): ?>
										<div class="col-12 col-sm-12 col-lg-4">
											<label for="name">Account Name:</label>
											<input type="text" class="form-control" id="name" name="name" autocomplete="off" value="<?php echo $account->getName(); ?>"/>
										</div>
									<?php endif; ?>
									<div class="col-12 col-sm-12 col-lg-5">
										<div class="form-check">
											<input type="checkbox"
												   name="c_pass"
												   id="c_pass"
												   value="false"
												   class="form-check-input"/>
											<label for="c_pass">Password: (check to change)</label>
										</div>
										<div class="input-group">
											<input type="text" class="form-control" id="pass" name="pass" autocomplete="off" maxlength="20" value=""/>
										</div>
									</div>
									<div class="col-12 col-sm-12 col-lg-3">
										<label for="account_id" class="control-label">Account ID:</label>
										<input type="text" class="form-control" id="account_id" name="account_id" autocomplete="off" size="8" maxlength="11" disabled value="<?php echo $account->getId(); ?>"/>
									</div>
								</div>
								<div class="form-group row">
									<?php
									$acc_group = $account->getAccGroupId();
									if ($hasTypeColumn) {
										?>
										<div class="col-12 col-sm-12 col-lg-6">
											<label for="group">Account Type:</label>
											<select name="group" id="group" class="form-control">
												<?php foreach ($acc_type as $id => $a_type): ?>
													<option value="<?php echo($id); ?>" <?php echo($acc_group == ($id) ? 'selected' : ''); ?>><?php echo $a_type; ?></option>
												<?php endforeach; ?>
											</select>
										</div>
										<?php
									} elseif ($hasGroupColumn) {
										?>
										<div class="col-12 col-sm-12 col-lg-6">
											<label for="group">Account Type:</label>
											<select name="group" id="group" class="form-control">
												<?php foreach ($groups->getGroups() as $id => $group): ?>
													<option value="<?php echo $id; ?>" <?php echo($acc_group == $id ? 'selected' : ''); ?>><?php echo $group->getName(); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
									<?php } ?>
									<div class="col-12 col-sm-12 col-lg-6">
										<label for="web_flags">Website Access:</label>
										<select name="web_flags" id="web_flags" class="form-control">
											<?php foreach ($web_acc as $id => $a_type): ?>
												<option value="<?php echo($id); ?>" <?php echo($account->getWebFlags() == ($id) ? 'selected' : ''); ?>><?php echo $a_type; ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								</div>
								<div class="form-group row">
									<?php if ($hasSecretColumn): ?>
										<div class="col-12 col-sm-12 col-lg-6">
											<label for="secret">Secret:</label>
											<input type="text" class="form-control" id="secret" name="secret" autocomplete="off" size="8" maxlength="11" value="<?php echo $account->getCustomField('secret'); ?>"/>
										</div>
									<?php endif; ?>
									<div class="col-12 col-sm-12 col-lg-6">
										<label for="key">Key:</label>
										<input type="text" class="form-control" id="key" name="key" autocomplete="off" size="8" maxlength="11" value="<?php echo $account->getCustomField('key'); ?>"/>
									</div>
								</div>
								<div class="form-group row">
									<div class="col-12 col-sm-12 col-lg-6">
										<label for="email">Email:</label>
										<input type="text" class="form-control" id="email" name="email" autocomplete="off" maxlength="20" value="<?php echo $account->getEMail(); ?>"/>
									</div>
									<?php if ($hasCoinsColumn): ?>
										<div class="col-12 col-sm-12 col-lg-6">
											<label for="t_coins">Tibia Coins:</label>
											<input type="text" class="form-control" id="t_coins" name="t_coins" autocomplete="off" maxlength="8" value="<?php echo $account->getCustomField('coins') ?>"/>
										</div>
									<?php endif; ?>
									<div class="col-12 col-sm-12 col-lg-6">
										<label for="p_days">Premium Days:</label>
										<input type="text" class="form-control" id="p_days" name="p_days" autocomplete="off" maxlength="11" value="<?php echo $account->getPremDays(); ?>"/>
									</div>
									<?php if ($hasPointsColumn): ?>
										<div class="col-12 col-sm-12 col-lg-6">
											<label for="p_points" class="control-label">Premium Points:</label>
											<input type="text" class="form-control" id="p_points" name="p_points" autocomplete="off" maxlength="8" value="<?php echo $account->getCustomField('premium_points') ?>"/>
										</div>
									<?php endif; ?>
								</div>
								<div class="form-group row">
									<div class="col-12 col-sm-12 col-lg-4">
										<label for="rl_name">RL Name:</label>
										<input type="text" class="form-control" id="rl_name" name="rl_name"
											   autocomplete="off" maxlength="20"
											   value="<?php echo $account->getRLName(); ?>"/>
									</div>
									<div class="col-12 col-sm-12 col-lg-4">
										<label for="rl_loca">Location:</label>
										<input type="text" class="form-control" id="rl_loca" name="rl_loca"
											   autocomplete="off" maxlength="20"
											   value="<?php echo $account->getLocation(); ?>"/>
									</div>
									<div class="col-12 col-sm-12 col-lg-4">
										<label for="rl_country">Country:</label>
										<select name="rl_country" id="rl_country" class="form-control">
											<?php foreach ($countries as $id => $a_type): ?>
												<option value="<?php echo($id); ?>" <?php echo($account->getCountry() == ($id) ? 'selected' : ''); ?>><?php echo $a_type; ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								</div>
								<div class="form-group row">
									<div class="col-12 col-sm-12 col-lg-6">
										<label for="created" class="control-label">Created:</label>
										<input type="text" class="form-control" id="created" name="created" autocomplete="off" maxlength="20" value="<?php echo date("M d Y, H:i:s", $account->getCustomField('created')); ?>"/>
									</div>
									<div class="col-12 col-sm-12 col-lg-6">
										<label for="web_lastlogin" class="control-label">Web Last Login:</label>
										<input type="text" class="form-control" id="web_lastlogin" name="web_lastlogin" autocomplete="off" maxlength="20" value="<?php echo date("M d Y, H:i:s", $account->getCustomField('web_lastlogin')); ?>"/>
									</div>
								</div>

								<input type="hidden" name="save" value="yes"/>

								<button type="submit" class="btn btn-info"><i class="fas fa-update"></i> Update</button>
								<a href="<?php echo ADMIN_URL; ?>?p=accounts" class="btn btn-danger float-right"><i class="fas fa-cancel"></i> Cancel</a>
							</form>
						</div>
						<div class="tab-pane fade" id="accounts-chars">
							<div class="row">
								<?php
								if (isset($account) && $account->isLoaded()) {
									$account_players = $account->getPlayersList();
									$account_players->orderBy('id');
									if (isset($account_players)) { ?>
										<table class="table table-striped table-condensed">
											<thead>
											<tr>
												<th>#</th>
												<th>Name</th>
												<th>Level</th>
												<th>Vocation</th>
												<th style="width: 40px">Edit</th>
											</tr>
											</thead>
											<tbody>
											<?php $i= 0;
											foreach ($account_players as $i => $player):
												$i++;
												$player_vocation = $player->getVocation();
												$player_promotion = $player->getPromotion();
												if (isset($player_promotion)) {
													if ((int)$player_promotion > 0)
														$player_vocation += ($player_promotion * $config['vocations_amount']);
												}

												if (isset($config['vocations'][$player_vocation])) {
													$vocation_name = $config['vocations'][$player_vocation];
												} ?>
												<tr>
													<th><?php echo $i; ?></th>
													<td><?php echo $player->getName(); ?></td>
													<td><?php echo $player->getLevel(); ?></td>
													<td><?php echo $vocation_name; ?></td>
													<td><a href="?p=players&id=<?php echo $player->getId() ?>" class=" btn btn-success btn-sm" title="Edit"><i class="fas fa-pencil-alt"></i></a></td>
												</tr>
											<?php endforeach ?>
											</tbody>
										</table>
										<?php
									}
								} ?>
							</div>
						</div>
						<?php if ($db->hasTable('bans')) : ?>
							<div class="tab-pane fade" id="accounts-bans">
								<?php
								$bans = $db->query('SELECT * FROM ' . $db->tableName('bans') . ' WHERE ' . $db->fieldName('active') . ' = 1 AND ' . $db->fieldName('id') . ' = ' . $account->getId() . ' ORDER BY ' . $db->fieldName('added') . ' DESC LIMIT 10');
								if ($bans->rowCount()) {
									?>
									<table class="table table-striped table-condensed">
										<thead>
										<tr>
											<th>Nick</th>
											<th>Type</th>
											<th>Expires</th>
											<th>Reason</th>
											<th>Comment</th>
											<th>Added by:</th>
										</tr>
										</thead>
										<tbody>
										<?php
										foreach ($bans as $ban) {
											?>
											<tr>
												<td><?php
													$pName = getPlayerNameByAccount($ban['value']);
													echo '<a href="?p=players&search=' . $pName . '">' . $pName . '</a>'; ?>
												</td>
												<td><?php echo getBanType($ban['type']); ?></td>
												<td>
													<?php
													if ($ban['expires'] == "-1")
														echo 'Never';
													else
														echo date("H:i:s", $ban['expires']) . '<br/>' . date("d M Y", $ban['expires']);
													?>
												</td>
												<td><?php echo getBanReason($ban['reason']); ?></td>
												<td><?php echo $ban['comment']; ?></td>
												<td>
													<?php
													if ($ban['admin_id'] == "0")
														echo 'Autoban';
													else
														$aName = getPlayerNameByAccount($ban['admin_id']);
													echo '<a href="?p=players&search=' . $aName . '">' . $aName . '</a>';
													echo '<br/>' . date("d.m.Y", $ban['added']);
													?>
												</td>
											</tr>
										<?php } ?>
										</tbody>
									</table>
									<?php
								} else {
									echo 'No Account bans.';
								} ?>
							</div>
						<?php endif;
						if ($db->hasTable('store_history')) { ?>
							<div class="tab-pane fade" id="accounts-store">
								<?php $store_history = $db->query('SELECT * FROM `store_history` WHERE `account_id` = "' . $account->getId() . '" ORDER BY `time` DESC')->fetchAll(); ?>
								<table class="table table-striped table-condensed">
									<thead>
									<tr>
										<th>Description</th>
										<th>Coins</th>
										<th>Date</th>
									</tr>
									</thead>
									<tbody>
									<?php foreach ($store_history as $p): ?>
										<tr>
											<td><?php echo $p['description']; ?></td>
											<td><?php echo $p['coin_amount']; ?></td>
											<td><?php echo date('d M y H:i:s', $p['time']); ?></td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	<?php } ?>
	<div class="col-12 col-sm-12 col-lg-2">
		<div class="card card-info card-outline">
			<div class="card-header">
				<h5 class="m-0">Search Accounts</h5>
			</div>
			<div class="card-body">
				<div class="row">
					<div class="col-6 col-lg-12">
						<form action="<?php echo $admin_base; ?>" method="post">
							<label for="name">Account Name:</label>
							<div class="input-group input-group-sm">
								<input type="text" class="form-control" name="search" value="<?php echo $search_account; ?>" maxlength="32" size="32">
								<span class="input-group-append"><button type="submit" class="btn btn-info btn-flat">Search</button></span>
							</div>
						</form>
					</div>
					<div class="col-6 col-lg-12">
						<form action="<?php echo $admin_base; ?>" method="post">
							<label for="name">Account ID:</label>
							<div class="input-group input-group-sm">
								<input type="text" class="form-control" name="id" value="" maxlength="32" size="32">
								<span class="input-group-append"><button type="submit" class="btn btn-info btn-flat">Search</button></span>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
	$(document).ready(function () {
		$('#created').datetimepicker({format: "M d Y, H:i:s",});
		$('#web_lastlogin').datetimepicker({format: 'M d Y, H:i:s'});

		$('#c_pass').change(function () {
			const ipass = $('input[name=pass]');
			ipass[0].disabled = !this.checked;
			ipass[0].value = '';
		}).change();

		$('.acc_datatable').DataTable({
			"order": [[0, "asc"]]
		});
	});
</script>

<?php
/**
 * REPL for PHP
 *
 * @author daddyofsky@gmail.com
 * @version 1.0
 */

// setting
$currentVersion = implode('.', array_slice(explode('.', PHP_VERSION), 0, 2));
$useEditor = !isset($_REQUEST['editor']) || $_REQUEST['editor'];
$allowIp = array();
$phpVersion = array(
	'web' => '',
	'5.3' => '/usr/local/bin/php53',
	'5.4' => '/usr/local/bin/php54',
	'5.6' => '/usr/local/bin/php56',
	'7.1' => '/usr/local/bin/php71',
	'7.4' => '/usr/local/bin/php74',
	'8.0' => '/usr/local/bin/php80',
	'8.1' => '/usr/local/bin/php81',
);

$Repl = new REPL($phpVersion);
if (!$Repl->hasAuth($allowIp)) {
	echo 'Access Denied!';
	exit;
}

// download stuff
if (isset($_GET['download'])) {
	try {
		downloadStuff();
		echo '<meta http-equiv="refresh" content="0; url=' . $_SERVER['PHP_SELF'] . '" />';
	} catch (RuntimeException $e) {
		echo $e->getMessage();
	}
	exit;
}

$version = !empty($_POST['version']) && is_array($_POST['version']) ? $_POST['version'] : array('web');
// post ajax
if ($_POST) {
	$output  = array();
	$code = preg_replace('/^<\?php\s*/', '', isset($_POST['code']) ? trim($_POST['code']) : '');
	if ($code) {
		foreach ($version as $v) {
			$output[$v] = $Repl->setVersion($v)->run($code);
		}
	}
	echo json_encode($output);
	exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>REPL for PHP</title>
	<?php loadStuff('mvp.css', '<link href="https://unpkg.com/mvp.css" rel="stylesheet" />'); ?>
	<style>
		:root.light {
			--color: #118bee;
			--color-accent: #118bee15;
			--color-bg: #fff;
			--color-bg-secondary: #e9e9e9;
			--color-link: #118bee;
			--color-secondary: #920de9;
			--color-secondary-accent: #920de90b;
			--color-shadow: #f4f4f4;
			--color-table: #118bee;
			--color-text: #000;
			--color-text-secondary: #999;
		}
		:root.dark {
			--color: #0097fc;
			--color-accent: #0097fc4f;
			--color-bg: #333;
			--color-bg-secondary: #555;
			--color-link: #0097fc;
			--color-secondary: #e20de9;
			--color-secondary-accent: #e20de94f;
			--color-shadow: #bbbbbb20;
			--color-table: #0097fc;
			--color-text: #f7f7f7;
			--color-text-secondary: #aaa;
		}

		header { padding:1rem; }
		h1, h2 { margin:0; }
		main { padding:1rem; }
		a b, a em, a i, a strong, button { padding:0.5rem 1rem; }
		button[type="submit"] { width:100px; }
		#code { width:750px; height:350px; font-size:14px; }
		.ace_editor { width:777px; height:350px; border:2px solid var(--color-bg-secondary); border-radius:var(--border-radius); }
		.ace_editor.on { border:2px solid var(--color-link); }
		pre { width:100%; margin:0; padding:0; }
		hr { margin:2rem 0; }
		input[type="checkbox"] + label { margin-right:10px; }
		#btn-history, #btn-reset { float:right; margin-left:8px; }

		#print header { position:relative; width:797px; }
		#print aside { width:70%; margin-top:0; }
		#print aside iframe { width:100%; min-height:50px; border:0 none; }
		.btn-toggle-html { position:absolute; top:9px; right:8px; }
		.btn-toggle-html i { padding: 2px 8px; }

		#history, #history_sample, #sample { display:none; }
		#history_list { width:797px; }
		#history_list code { max-height:100px; overflow:auto; cursor:pointer; }
		#history header { width:797px; }
		#history header, #history pre { position:relative; }
		#history a { position:absolute; right:0; }
		#history i { padding: 2px 8px; }
		.btn-clear-history { top:-5px; }
		#btn-theme { position:absolute; right:0; top:0; }
		#btn-theme i { padding:2px 8px; }
	</style>
</head>
<body>
	<header>
		<h1>REPL for PHP</h1>
		<a href="#" id="btn-theme"><i>Dark Mode</i></a>
	</header>
	<main>
		<section>
			<form method="post">
				<label>Version</label>
				<?php if ($versions = $Repl->getVersionList()): ?>
					<?php if (count($versions) > 1): ?>
						<input type="checkbox" id="version-all"><label for="version-all">All</label>
					<?php endif ?>
					<?php foreach ($versions as $v): ?>
						<?php if ($v === 'web'): ?>
							<input type="checkbox" id="version-web" name="version[]" value="<?=$v?>"><label for="version-web">Web (<?=$currentVersion?>)</label>
						<?php else: ?>
							<input type="checkbox" id="version<?=$v?>" name="version[]" value="<?=$v?>"><label for="version<?=$v?>"><?=$v?></label>
						<?php endif ?>
					<?php endforeach ?>
				<?php endif ?>

				<label for="code">Code</label>
				<textarea id="code" name="code">&lt;?php
</textarea>
				<p>
					<button type="submit">RUN</button>
					<a href="#" id="btn-reset"><i>RESET</i></a>
					<a href="#" id="btn-history"><i>HISTORY</i></a>
				</p>
			</form>
		</section>

		<div id="history">
			<hr />
			<section>
				<header>
					<h2>History</h2>
					<a href="#" class="btn-clear-history"><i>Clear History</i></a>
				</header>
				<pre id="history_sample">
					<a href="#" class="btn-delete-history"><i>&times</i></a>
					<code></code>
				</pre>
				<div id="history_list"></div>
			</section>
		</div>

		<div id="sample">
			<hr />
			<section>
				<header>
					<h2></h2>
					<a href="#" class="btn-toggle-html"><i>View</i></a>
				</header>
				<aside style="display:none;"><iframe></iframe></aside>
				<pre><code></code></pre>
			</section>
		</div>
		<div id="print"></div>
	</main>
	<?php loadStuff('jquery-3.6.0.min.js', '<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>'); ?>
	<?php if ($useEditor): ?>
		<?php loadStuff('ace.min.js', '<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.13/ace.min.js" integrity="sha512-jB1NOQkR0yLnWmEZQTUW4REqirbskxoYNltZE+8KzXqs9gHG5mrxLR5w3TwUn6AylXkhZZWTPP894xcX/X8Kbg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>'); ?>
		<?php loadStuff('ext-language_tools.min.js', '<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.13/ext-language_tools.min.js" integrity="sha512-S7Whi8oQAQu/MK6AhBWufIJIyOvqORj+/1YDM9MaHeRalsZjzyYS7Usk4fsh+6J77PUhuk5v/BxaMDXRdWd1KA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>'); ?>
		<?php loadStuff('theme-sqlserver.min.js', '<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.13/theme-sqlserver.min.js" integrity="sha512-5/Pr8klgzFtEe+UtAJ6x7r1N1FCvMM/z7iduB1HIz7YaOGZyRlaAXZGUSuU2bzTW7fSlvfFsiFIVBDyoEbvlJg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>'); ?>
		<?php loadStuff('theme-dracula.min.js', '<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.13/theme-dracula.min.js" integrity="sha512-spJzhyfxwWqVa1Tab7js2JKLQD6V5Q1Bsd5QQCJ14b7uw4bOoIPSvR9skHgHNuf2c9AIWR28EzhqvCuc24hUnA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>'); ?>
		<?php loadStuff('mode-php.min.js', '<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.13/mode-php.min.js" integrity="sha512-bHkP9bEx5NadBxlEYjyfopFP1PiC+x70XWiZ5laCCCVfp/+tSSiKidMLfsBAJYcX2AEuDq+o/gdxsyInslP6OQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>'); ?>
		<?php loadStuff('snippets/php.min.js', '<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.13/snippets/php.min.js" integrity="sha512-BONX43FZNR4rCdkEiUtqmRScNav4lyEeJCBNUowoLR8OG0HvQh5Zst1IL/Jg+/ymUNzQOtmEWMFjhOvRxxtc9w==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>'); ?>
	<?php endif ?>
	<script type="text/javascript">
		$(function() {
			var version = <?=json_encode($version)?>;
			version.forEach(function(v) {
				$('input[name="version[]"][value="' + v + '"]').prop('checked', true);
			});
			$('#version-all').on('click', function() {
				var checked = $(this).prop('checked');
				$(this).nextAll(':checkbox').prop('checked', checked);
				$('input[name="version[]"][value="current"]').prop('checked', true);
			});

			REPL.initEditor();

			$('form').on('submit', function(e) {
				e.preventDefault();
				$('#history').hide();
				REPL.run(this);
			});
			$('#btn-reset').on('click', function(e) {
				e.preventDefault();
				REPL.reset();
				$('#history, #print').hide();
			});
			$('#btn-history').on('click', function(e) {
				e.preventDefault();

				if (!$('#history').toggle().is(':visible')) {
					return;
				}

				(REPL.get() || []).forEach(function(code, index) {
					var $area = $('#history_list');
					if (!$area.find('[data-index="' + index + '"]').length) {
						var $row = $('#history_sample').clone(true).removeAttr('id')
							.attr('data-index', index)
							.find('code').text(code).end()
							.on('click', function() {
								REPL.set($(this).find('code').text())
								document.body.scrollIntoView();
							})
						if (index) {
							$row.insertBefore($area.children(':first'));
						} else {
							$row.appendTo($area);
						}
					}
				});
			});
			$('.btn-clear-history').on('click', function(e) {
				e.preventDefault();
				if (confirm('Are you sure to clear all history?')) {
					REPL.clear();
					$('#history_list').html('');
				}
			});
			$('.btn-delete-history').on('click', function(e) {
				e.preventDefault();
				var $row = $(this).closest('[data-index]');
				if ($row.length) {
					var index = parseInt($row.data('index'), 10);
					if (index > -1) {
						REPL.delete(index);
						$row.remove();
					}
				}
			});

			$('#btn-theme').on('click', function(e) {
				e.preventDefault();
				changeColorScheme();
			})

			// detect color scheme
			if (window.matchMedia) {
				var checkDark = window.matchMedia('(prefers-color-scheme: dark)');
				checkDark.addListener(function(e) {
					changeColorScheme(e.matches ? 'dark' : 'light');
				});
				if (checkDark.matches) {
					changeColorScheme('dark');
				}
			}
		});

		function changeColorScheme(scheme) {
			if ((scheme && scheme === 'light') || $(document.documentElement).hasClass('dark')) {
				$(document.documentElement).removeClass('dark').addClass('light');
				REPL.editor.setTheme('ace/theme/sqlserver');
				$(this).find('i').html('Light Mode');
			} else {
				$(document.documentElement).removeClass('light').addClass('dark');
				REPL.editor.setTheme('ace/theme/dracula');
				$(this).find('i').html('Dark Mode');
			}
		}

		var REPL = {
			editor : null,
			object : '#code',
			dbKey : 'repl_code',
			index : -1,
			length : 0,
			maxCount : 50,
			initEditor : function() {
				if (typeof ace !== 'object') {
					return;
				}
				var $clone = $(this.object).hide().clone(true).insertAfter(this.object);
				ace.require('ace/ext/language_tools');
				this.editor = ace.edit($($clone).get(0), {
					mode: 'ace/mode/php',
					theme: 'ace/theme/sqlserver',
					tabSize: 4,
					fontSize: 14,
					enableBasicAutocompletion: true,
					enableLiveAutocompletion: true,
					enableSnippets: true,
					useWorker: false
				});
				this.editor.on('blur', function() {
					$(REPL.object).val(REPL.editor.getValue());
					$('.ace_editor').removeClass('on');
				});
				this.editor.on('focus', function() {
					$('.ace_editor').addClass('on');
				});
			},
			run : function(form) {
				$.ajax({
					url: location.href,
					type: 'POST',
					dataType: 'json',
					data: $(form).serialize(),
					success: function(r) {
						REPL.print(r);
					},
					error: function(r) {
						REPL.error(r.responseText || 'No Result');
					}
				});
			},
			print : function(r) {
				$('#print').html('').show();
				this.save($(this.object).val());
				for (var version in r) {
					if (r.hasOwnProperty(version)) {
						this.printOne(version, r[version]);
					}
				}
			},
			printOne : function(version, output) {
				if (version === 'web') {
					version = 'Web (<?=$currentVersion?>)';
				}
				var a = $('#sample').clone(true).removeAttr('id')
					.find('h2').text(version).end()
					.find('code').text(output).end()
					.find('.btn-toggle-html').on('click', function(e) {
						e.preventDefault();
						var $iframe = $(this).parent().next('aside').toggle().find('iframe');
						if ($iframe.prop('init') === undefined) {
							$iframe.contents().find('body').html(output).on('load', function() {
								console.log('load');
								$iframe.prop('init', 1).height($(this).height() + 30);
							}).trigger('load');
						}
						$(this).find('i').text($iframe.is(':visible') ? 'Hide' : 'View');
					}).end()
					.appendTo('#print');
			},
			error : function(msg) {
				this.printOne('ERROR', msg);
			},
			save : function(code) {
				code = code.trim();
				if (!code || code === '<' + '?php') {
					return;
				}
				var data = this.get();
				if (data.indexOf(code) === -1) {
					data.push(code);
					if (data.length > this.maxCount) {
						data = data.slice(data.length - this.maxCount);
					}
				}
				this.length = data.length;
				this.index = this.length - 1;
				window.localStorage.setItem(this.dbKey, JSON.stringify(data));
			},
			load : function(index) {
				if (typeof index !== 'number') {
					index = this.index;
				}
				var code = this.get(index);
				this.set(code);
			},
			reset : function() {
				var code = '<' + '?php';
				this.set(code);
			},
			set : function(code) {
				code += "\n";
				$(this.object).val(code);
				if (this.editor) {
					this.editor.setValue(code, 1);
				}
			},
			get : function(index) {
				var data = JSON.parse(window.localStorage.getItem(this.dbKey) || '[]');
				this.length = data.length;
				if (typeof index === 'number') {
					if (index < 0 || index >= this.length) {
						index = this.length - 1;
					}
					return data[index];
				}
				return data;
			},
			delete : function(index) {
				var data = JSON.parse(window.localStorage.getItem(this.dbKey) || '[]');
				if (typeof index === 'number') {
					data.splice(index, 1);
				}
				this.length = data.length;
				window.localStorage.setItem(this.dbKey, JSON.stringify(data));
			},
			clear : function() {
				window.localStorage.setItem(this.dbKey, '[]');
			}
		}
	</script>
</body>
</html>
<?php
/**
 * REPL(read-eval-print loop) class for local PHP dev
 */
class REPL
{
	/** @var string */
	protected $version = '';

	/** @var array */
	protected $versions = array();

	/** @var array */
	protected $allowIp = array(
		'127.0.0.1',
	);

	/**
	 * constructor
	 *
	 * @param string $version
	 */
	public function __construct($versions = array())
	{
		if ($versions) {
			$this->versions = $versions;
		}
	}

	/**
	 * has auth
	 *
	 * @param array $allowIp
	 * @return bool
	 */
	public function hasAuth($allowIp = array())
	{
		$this->allowIp = array_merge($this->allowIp, (array)$allowIp);
		return in_array($_SERVER['REMOTE_ADDR'], $this->allowIp, true);
	}

	/**
	 * get version
	 *
	 * @return array
	 */
	public function getVersionList()
	{
		return array_keys($this->versions);
	}

	/**
	 * set version
	 *
	 * @param string $version
	 * @return $this
	 */
	public function setVersion($version)
	{
		if (isset($this->versions[$version])) {
			$this->version = $version;
		}
		return $this;
	}

	/**
	 * run
	 *
	 * @param string $code
	 * @param bool $output
	 * @return bool|string
	 */
	public function run($code, $output = false)
	{
		if (!$code) {
			return false;
		}

		ob_start();
		try {
			if ($this->version && !empty($this->versions[$this->version])) {
				passthru($this->versions[$this->version] . ' -r ' . escapeshellarg($code));
			} else {
				// TODO : error hander
				eval($code);
			}
		} catch (Exception $e) {
			// ignore
		}

		if ($output) {
			return ob_end_flush();
		}
		return ob_get_clean();
	}
}

/**
 * load js
 *
 * @param string $file
 * @param string $cdnHtml
 */
function loadStuff($file, $cdnHtml)
{
	$dir = __DIR__ . '/repl';
	if (file_exists($dir . '/' . $file)) {
		if (preg_match('/\.css/', $file)) {
			echo '<link rel="stylesheet" href="repl/' . $file . '" />';
		} else {
			echo '<script type="text/javascript" src="repl/' . $file . '"></script>';
		}
	} else {
		echo $cdnHtml;
	}
	echo "\n";
}

function downloadStuff()
{
	$src = file_get_contents(__FILE__);
	if (preg_match_all('/\hloadStuff\(\'([^\']+)\',\h+\'<\w+\h+(?:src|href)="(https?:[^"]+)"/', $src, $match)) {
		$dir = __DIR__ . '/repl';
		if (!is_dir($dir) && !mkdir($dir, 0707) && !is_dir($dir)) {
			throw new RuntimeException('Fail to make directory : ./repl');
		}

		ob_start();
		foreach ($match[2] as $key => $url) {
			$file = $match[1][$key];
			if ($sub = dirname($file)) {
				$tmp = $dir . '/' . $sub;
				if (!is_dir($tmp) && !mkdir($tmp, 0707) && !is_dir($tmp)) {
					throw new RuntimeException('Fail to make directory : ./repl/' . $sub);
				}
			}

			$path = $dir . '/' . $file;
			if (file_exists($path)) {
				continue;
			}

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($ch);
			$r = file_put_contents($path, $response);
			if (!$r) {
				throw new RuntimeException('Fail to write file : ./repl/' . $file);
			}
		}
		ob_end_clean();
	}
}
<?php
/**
 *
 * Usage: see readme.txt
 *
 * Add your ip to the $whitelist_addr list below
 */

$whitelist_addr = array("127.0.0.1");
if (!in_array($_SERVER['REMOTE_ADDR'], $whitelist_addr)) {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

@session_start();
ob_start();
date_default_timezone_set("Europe/Amsterdam");

// Define this url
list($current_url) = explode('?', $_SERVER["REQUEST_URI"]);
// Define action
$action = (isset($_REQUEST['action']) ? $_REQUEST['action'] : "default");
if (!in_array($action, array("zip", "unzip", "config", "dbimport", "dbimportfinished", "default"))) return "Undefined task";


// Switch action
switch ($action) {
    case "zip":
        if (!isset($_POST['zipform'], $_POST['file'], $_POST['excludes'])) {
            // Give Zipform
            ?>
            <form class="form-horizontal" role="form" action="?action=zip" method="post">
                <div class="form-group">
                    <label for="file" class="col-sm-2 control-label">Zip filename</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" name="file" id="file" value="<?php echo "../" . date("Ymd-His") . "-" . strtolower($_SERVER['HTTP_HOST']) . ".zip"; ?>">
                        <span class="help-block">Be sure to save it in a safe location (../ is outside public folder).</span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="excludes" class="col-sm-2 control-label">Exclude files/folders</label>
                    <div class="col-sm-10">
                        <textarea class="form-control" name="excludes" rows="3"><?php
                            echo    str_replace(realpath("./") . "/", "", __FILE__) . PHP_EOL .
                                "core/cache" . PHP_EOL .
                                "assets/components/phpthumbof/cache";
                            ?></textarea>
                        <span class="help-block">Line-break separated list. Directories without trailing slash.</span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <div class="checkbox">
                            <?php if (file_exists(dirname(__FILE__).'/config.core.php')) { ?>
                                <label>
                                    <input type="checkbox" name="db" value="1" checked> Backup database.
                                </label>
                                <span class="help-block">Check afterwards if 'export-db' folder is removed, the db is in it.</span>
                            <?php } else { ?>
                                <div class="alert-warning"><p>Do MODx config file found (config.core.php), so no db backup can be done.</p></div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="unlink" value="1" checked> <b>Remove this script</b> after execution.
                            </label>
                            <span class="help-block">Copy this script to new backup location for extraction and configuration steps.</span>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="zipform" value="1">
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button type="submit" class="btn btn-primary" onclick="this.className += 'disabled';">Start Backup</button>
                    </div>
                </div>
            </form>
        <?php
        } else {
            // Dump Database
            if (isset($_POST["db"]) && $_POST["db"] == "1") {
                // Include MODx
                define('MODX_REQP',false);
                /** @var $modx modX */
                require_once dirname(__FILE__).'/config.core.php';
                require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
                require_once MODX_CONNECTORS_PATH . 'index.php';

                // Create dump
                $exportfolder = './export-db/';
                $tmpfolder = $exportfolder . 'tmp/';
                if (!file_exists($exportfolder)) { mkdir($exportfolder, 0777, true); }
                if (!file_exists($tmpfolder)) { mkdir($tmpfolder, 0777, true); }
                // Protect db
                file_put_contents($exportfolder . ".htaccess", "order deny,allow" . PHP_EOL . "deny from all" . PHP_EOL);

                // run sql dump
                $modx->runSnippet('backup', array(
                    'dataFolder' => $exportfolder,
                    'tempFolder' => $tmpfolder,
                    'createDatabase' => false,
                    'writeTableFiles' => false,
                    'writeFile' => true
                ));
                if (delTree($tmpfolder) == false)
                    addMessage("danger", "<p><b>DB not exported.</b></p>");
                else
                    addMessage('success', '<p>DB exported.</p>');
            }

            // Zip it!
            $excluded_files = preg_split('/\r\n|[\r\n]/', $_POST['excludes']);
            my_zip($_POST["file"], $excluded_files);
            addMessage('success', '<p>Folder zipped.</p>');

            // remove db folder
            if (isset($exportfolder) && strlen($exportfolder) > 3) {
                if (delTree($exportfolder) == false)
                    addMessage("danger", "<p><b>DB export (./export-db/) not deleted.</b> Please do so manually.</p>");
                else
                    addMessage('success', '<p>DB export deleted.</p>');
            }

            if (isset($_POST["unlink"]) && $_POST["unlink"] == "1") {
                if (unlink(__FILE__) == false)
                    addMessage("danger", "<p><b>Script not deleted.</b> Please do so manually.</p>");
                else
                    addMessage('success', '<p>Script deleted.</p>');
            }

            addMessage("info", "<p>On success: Copy the created zip and this script to the root of the new location.</p>");
            addMessage("warning", "<p>Make sure you don't leave this script on the server.</p>");
        }

        break;
    case "unzip":

        if (!isset($_POST['extractform'])) {


            // Give Zipform
            $files = preg_grep('/^([^.])/', scandir(dirname(__FILE__)));
            $files = is_array($files) ? $files : array();
            $zipfiles = array();
            foreach ($files as $file) {
                if (strtolower(substr($file,-3)) === "zip")
                    $zipfiles[] = $file;
            }
            ?>
            <form class="form-horizontal" role="form" action="?action=unzip" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="ziplocation" class="col-sm-2 control-label">Zip from disk (prio-1):</label>
                    <div class="col-sm-10">
                        <?php
                        if (count($zipfiles) <= 0) {
                            echo "<p class=\"text-warning\">No 'zip'files found in the current directory.</p>";
                        } else {
                            echo '<select name="ziplocation" class="form-control">';
                            foreach ($zipfiles as $zipfile) {
                                echo '<option>'.$zipfile.'</option>';
                            }
                            echo '</select>';
                        }
                        ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="file" class="col-sm-2 control-label">Zip from upload (prio-2):</label>
                    <div class="col-sm-10">
                        <input type="file" class="form-control" name="file" id="file">
                    </div>
                </div>
                <div class="form-group">
                    <label for="output" class="col-sm-2 control-label">Output folder:</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" name="output" id="output" value="./">
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="unlink" value="1" checked> <b>Remove this zip</b> after successful extraction.
                            </label>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="extractform" value="1">
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button type="submit" class="btn btn-primary" onclick="this.className += 'disabled';">Start Unzip</button>
                    </div>
                </div>
            </form>
        <?php
        } else if (isset($_POST['extractform'])) {
            if (isset($_POST['ziplocation']) && file_exists($_POST['ziplocation'])) {
                $file = $_POST['ziplocation'];
            } else if ($_FILES["file"]["error"] <= 0 && file_exists($_FILES["file"]["tmp_name"])) {
                $file = $_FILES["file"]["tmp_name"];
            } else {
                addMessage("danger", "<p>No valid file for extract process.</p>");
                break;
            }

            // extract
            $destination = $_POST['output'];
            if (my_unzip($file, $destination)) {
                addMessage("success", "<p>Successfully extracted '$file' to '$destination'.</p>");
                if (isset($_POST['unlink']) && $_POST['unlink'] === "1") {
                    unlink($file);
                    addMessage("success", "<p>Successfully removed '$file'.</p>");
                }
            } else {
                addMessage("danger", "<p>Failed to extract. Check file(read) and folder(write) permissions.</p>");
            }
        }

        break;
    case "config":

        $core_config_file = dirname(__FILE__). DIRECTORY_SEPARATOR . 'config.core.php';
        $conn_config_file = dirname(__FILE__). DIRECTORY_SEPARATOR . 'connectors'. DIRECTORY_SEPARATOR .'config.core.php';
        $man_config_file = dirname(__FILE__). DIRECTORY_SEPARATOR . 'manager'. DIRECTORY_SEPARATOR .'config.core.php';
        $htaccess_file    = dirname(__FILE__). DIRECTORY_SEPARATOR . '.htaccess';

        $core_dir       = dirname(__FILE__) . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR;
        if (!file_exists($htaccess_file) || !file_exists($core_config_file) || !file_exists($man_config_file) || !file_exists($conn_config_file) || !is_dir($core_dir)) {
            addMessage("danger", "<p>One of '.htaccess/config files' or 'core folder' is not found.</p>");
            break;
        }

        require_once $core_config_file;
        $core_config_inc = $core_dir . 'config' . DIRECTORY_SEPARATOR . MODX_CONFIG_KEY . '.inc.php';
        require_once $core_config_inc;

        $res = preg_match("/http_host='(.*)';/", file_get_contents($core_config_inc), $host_matches);
        if ($res == 0 || !array_key_exists(1, $host_matches)) {
            addMessage("danger", "<p>http_host variable could not be extracted from config file.</p>");
            break;
        }
        $http_host = $host_matches[1];

        if (!isset($_POST['configform'])) {

            ?>
            <form class="form-horizontal" role="form" action="?action=config" method="post">
                <div class="form-group">
                    <label for="configkey" class="col-sm-2 control-label">MODX CONFIG KEY:</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" name="configkey" id="configkey" value="<?php echo MODX_CONFIG_KEY ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="corepath" class="col-sm-2 control-label">MODX CORE PATH:</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" name="corepath" id="corepath" value="<?php echo MODX_CORE_PATH ?>">
                        <span class="help-block" id="corepathsuggestion">Suggestion: <a href="#" title="Click to activate."><?php echo $core_dir ?></a>.</span>
                        <script type="application/javascript">
                            $(document).ready(function () {
                                $("#corepathsuggestion").on("click", function (e) {
                                    e.preventDefault();
                                    $("#corepath").val("<?php echo $core_dir ?>");
                                })
                            });
                        </script>
                    </div>
                </div>
                <div class="form-group">
                    <label for="httphost" class="col-sm-2 control-label">HTTP HOST:</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" name="httphost" id="httphost" value="<?php echo $http_host ?>">
                        <span class="help-block" id="httphostsuggestion">Suggestion: <a href="#" title="Click to activate."><?php echo $_SERVER['SERVER_NAME'] ?></a>.</span>
                        <script type="application/javascript">
                            $(document).ready(function () {
                                $("#httphostsuggestion").on("click", function (e) {
                                    e.preventDefault();
                                    $("#httphost").val("<?php echo $_SERVER['SERVER_NAME'] ?>");
                                })
                            });
                        </script>
                    </div>
                </div>
                <div class="form-group">
                    <label for="dbserver" class="col-sm-2 control-label">Database Server:</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" name="dbserver" id="dbserver" value="<?php echo $database_server ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="dbuser" class="col-sm-2 control-label">Database User:</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" name="dbuser" id="dbuser" value="<?php echo $database_user ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="dbpassword" class="col-sm-2 control-label">Database Password:</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" name="dbpassword" id="dbpassword" value="<?php echo $database_password ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="db" class="col-sm-2 control-label">Database:</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" name="db" id="db" value="<?php echo $dbase ?>">
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="htaccess" value="1"> Change htaccess.
                            </label>
                            <span class="help-block">Example: change <i>olddomain.com</i> to <i>newdomain.com</i></span>
                        </div>
                    </div>
                </div>
                <script type="application/javascript">
                    $(document).ready(function () {
                        $("input[name='htaccess']").on("change", function (e) {
                            if ($(e.target).prop("checked")) {
                                $("#htaccess_content").show();
                            } else {
                                $("#htaccess_content").hide();
                            }
                        });
                    });
                </script>
                <div class="form-group" id="htaccess_content" style="display: none;">
                    <label for="htaccess_content" class="col-sm-2 control-label">.htaccess file</label>
                    <div class="col-sm-10">
                        <textarea class="form-control" name="htaccess_content"
                                  rows="10" style="font-family: Consolas, Monaco, Courier, monospace;"><?php
                            echo file_get_contents($htaccess_file)
                            ?></textarea>
                    </div>
                </div>
                <input type="hidden" name="configform" value="1">
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button type="submit" class="btn btn-primary" onclick="this.className += 'disabled';">Change Config</button>
                    </div>
                </div>
            </form>
        <?php
        } else if (isset($_POST['configform'])) {

            // check db connection
            $new_dsn = "mysql:host={$_POST['dbserver']};dbname={$_POST['db']};charset={$database_connection_charset}";
            try {
                $db = new PDO($new_dsn, $_POST['dbuser'], $_POST['dbpassword']);
            } catch (PDOException $e) {
                print_r(PDO::getAvailableDrivers());
                addMessage("danger", "<p>Could not connect to mysql server or database.</p><p>{$e->getMessage()}</p>");
                break;
            }
            $db = null;

            // config.core.php
            $sr = array(
                sQuote(MODX_CONFIG_KEY) => sQuote($_POST['configkey']),
                sQuote(MODX_CORE_PATH) => sQuote($_POST['corepath'])
            );
            $c1 = file_put_contents($core_config_file, str_replace(array_keys($sr), array_values($sr), file_get_contents($core_config_file)));
            $c2 = file_put_contents($conn_config_file, str_replace(array_keys($sr), array_values($sr), file_get_contents($conn_config_file)));
            $c3 = file_put_contents($man_config_file, str_replace(array_keys($sr), array_values($sr), file_get_contents($man_config_file)));

            if ($c1 == false || $c2 == false || $c3 == false)
                addMessage("danger", "<p>One or more config files failed.</p>");
            else
                addMessage("success", "<p>All config files successfully changed.</p>");


            // core config.inc.php
            $modx_root_path = substr(MODX_CORE_PATH, 0, strlen(MODX_CORE_PATH) - 5);
            $new_modx_root_path = substr($_POST['corepath'], 0, strlen($_POST['corepath']) - 5);
            $sr = array(
                "database_server = ".sQuote($database_server)    => "database_server = ".sQuote($_POST['dbserver']),
                "database_user = ".sQuote($database_user)           => "database_user = ".sQuote($_POST['dbuser']),
                "database_password = ".sQuote($database_password)  => "database_password = ".sQuote($_POST['dbpassword']),
                "dbase = ".sQuote($dbase)                           => "dbase = ".sQuote($_POST['db']),
                "database_dsn = ".sQuote($database_dsn)         => "database_dsn = ".sQuote($new_dsn),
                "'" . $modx_root_path        => "'" . $new_modx_root_path,
                "http_host=" . sQuote($http_host) => "http_host=" . sQuote($_POST['httphost'])
            );

            $c4 = file_put_contents($core_config_inc, str_replace(array_keys($sr), array_values($sr), file_get_contents($core_config_inc)));
            if ($c4 == false)
                addMessage("danger", "<p>The main config file failed.</p>");
            else
                addMessage("success", "<p>The main config file successfully changed.</p>");

            // Htaccess
            if (isset($_POST['htaccess']) && $_POST['htaccess'] == "1") {
                $h1 = file_put_contents($htaccess_file, $_POST['htaccess_content']);
                if ($h1 == false)
                    addMessage("danger", "<p>The .htaccess file failed.</p>");
                else
                    addMessage("success", "<p>The .htaccess file successfully changed.</p>");
            }

            // Make writable dirs
            $writable_dirs = array("core/cache", "assets/components/phpthumbof/cache", "assets/uploads");
            foreach ($writable_dirs as $dir) {
                $full_dir = $new_modx_root_path . $dir;
                if (!is_dir($full_dir)) {
                    addMessage("info", "<p>$dir does not exist, should be writable.</p>");
                    continue;
                }

                if (!is_writable($full_dir)) {
                    if (chmod($full_dir, 0775))
                        addMessage("success", "<p>$dir is now writable.</p>");
                    else
                        addMessage("warning", "<p>$dir not writable.</p>");
                }
            }

        }
        break;
    case "dbimport":

        if (!isset($_POST['dbform'])) {

            addMessage("info", "<p>The existing database should be empty before submit.</p>");

            // Scan backup
            $files = directoryToArray("export-db");
            $sqlfiles = array();
            foreach ($files as $file) {
                if (strtolower(substr($file,-3)) === "sql")
                    $sqlfiles[] = $file;
            }
            ?>
            <form class="form-horizontal" role="form" action="?action=dbimport" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="sqllocation" class="col-sm-2 control-label">SQL file from disk (prio-1):</label>
                    <div class="col-sm-10">
                        <?php
                        if (count($sqlfiles) <= 0) {
                            echo "<p class=\"text-warning\">No 'sql'files found in the 'export-db' directory.</p>";
                        } else {
                            echo '<select name="sqllocation" class="form-control">';
                            foreach ($sqlfiles as $sqlfile) {
                                echo '<option>'.$sqlfile.'</option>';
                            }
                            echo '</select>';
                        }
                        ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="file" class="col-sm-2 control-label">SQL file from upload (prio-2):</label>
                    <div class="col-sm-10">
                        <input type="file" class="form-control" name="file" id="file">
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="entry" value="1" checked> Update `workspaces` table entry (change existing to new core path) after successful import.
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="unlinksql" value="1" checked> <b>Remove export-db folder</b> after successful import.
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="unlinkscript" value="1" checked> <b>Remove this script</b> after successful import.
                            </label>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="dbform" value="1">
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button type="submit" class="btn btn-primary" onclick="this.className += 'disabled';">Start Import</button>
                    </div>
                </div>
            </form>
            <?php
        } else if (isset($_POST['dbform'])) {

            if (isset($_POST['sqllocation']) && file_exists($_POST['sqllocation'])) {
                $file = $_POST['sqllocation'];
            } else if ($_FILES["file"]["error"] <= 0 && file_exists($_FILES["file"]["tmp_name"])) {
                $file = $_FILES["file"]["tmp_name"];
            } else {
                addMessage("danger", "<p>No valid file for import process.</p>");
                break;
            }

            // Download bigdump
            cURLdownload("https://raw2.github.com/voltan/code/master/bigdump/bigdump.php", "bigdump.php");
            if (file_exists("bigdump.php")) {

                // modify bigdump
                require_once "config.core.php";
                require_once MODX_CORE_PATH . 'config' . DIRECTORY_SEPARATOR . MODX_CONFIG_KEY . '.inc.php';

                $sr = array(
                    "/db_server\s*=\s*'(.*)';/" => "db_server = '$database_server';",
                    "/db_name\s*=\s*'(.*)';/" => "db_name = '$dbase';",
                    "/db_username\s*=\s*'(.*)';/" => "db_username = '$database_user';",
                    "/db_password\s*=\s*'(.*)';/" => "db_password = '$database_password';",
                    "/filename\s*=\s*'(.*)';/" => "filename = '$file';"
                );

                $c1 = file_put_contents("bigdump.php", preg_replace(array_keys($sr), array_values($sr), file_get_contents("bigdump.php")));
                if ($c1 == false)
                    addMessage("danger", "<p>BigDump preparation failed.</p>");
                else {
                    addMessage("success", "<p>BigDump successfully downloaded and prepared. Use BigDump to import the database.</p>");

                    // generate finish url
                    $finish_url = $current_url . "?" . http_build_query(array(
                            "action" => $action . "finished",
                            "entry" => $_POST['entry'],
                            "uexp" => $_POST['unlinksql'],
                            "uscr" => $_POST['unlinkscript']
                        ));
                    ?>
                    <div class="row-fluid">
                        <iframe name="bigdump" id="bigdump" class="container well well-small span6"
                                style="height: 400px; background-color: #f5e5c5;"
                                src="bigdump.php">
                        </iframe>
                    </div>
                    <script type="application/javascript">
                        $("#bigdump").load(function () {
                            var start_el = $("a:contains('Start Import')", frames["bigdump"].document),
                                finish_el = $("p:contains('Congratulations: End of file reached, assuming OK')", frames["bigdump"].document);

                            if (start_el.length > 0) {
                                $("#bigdump").attr("src", start_el.eq(0).attr('href'));
                            }

                            if (finish_el.length > 0) {
                                window.location.href = '<?php echo stripslashes($finish_url) ?>';
                            }
                        });
                    </script>
                <?php
                }

            }
            else
                addMessage("danger", "<p>Failed to download BigDump. Import stopped.</p>");

        }
        break;
    case "dbimportfinished":
        addMessage("success", "<p>Database imported successfully.</p>");

        // Cleanup
        if (unlink("bigdump.php") == false)
            addMessage("danger", "<p>Failed to delete 'bigdump.php'. <b>Security Risk!</b> Remove manually.</p>");
        if (isset($_GET['uexp']) && $_GET['uexp'] == "1")
            if (delTree(dirname(__FILE__) . DIRECTORY_SEPARATOR . "export-db") == false)
                addMessage("warning", "<p>Failed to delete 'export-db/'. Remove manually.</p>");
            else
                addMessage("success", "<p>Successfully deleted 'export-db/'.</p>");
        if (isset($_GET['uscr']) && $_GET['uscr'] == "1")
            if (unlink(__FILE__) == false)
                addMessage("warning", "<p>Failed to delete this script. <b>Security Risk!</b> Remove manually.");
            else
                addMessage("success", "<p>Successfully deleted this script.</p>");

        // Update workspace table (core path in 1 entry)
        if (isset($_GET['uscr']) && $_GET['uscr'] == "1") {
            // import required files
            require_once "config.core.php";
            require_once MODX_CORE_PATH . 'config' . DIRECTORY_SEPARATOR . MODX_CONFIG_KEY . '.inc.php';

            // change db entry
            try {
                $db = new PDO($database_dsn, $database_user, $database_password);
                $db->exec("UPDATE modx_workspaces SET path=".sQuote(MODX_CORE_PATH)." WHERE id=1;");
                addMessage("success", "<p>Successfully updated `workspace` entry to new core folder.</p>");
            } catch (PDOException $e) {
                addMessage("danger", "<p>Could not update workspace entry due to: Could not connect to mysql server or database.</p><p>{$e->getMessage()}</p>");
                break;
            }
            $db = null;
        }

        // Next steps
        addMessage("info", "<p>If no errors occurred, you can reach <a href=\"".str_replace(pathinfo(__FILE__, PATHINFO_BASENAME), "", $current_url)."\">the working modx installation.</a></p>");

        break;
    case "default":
        ?>
        <h3>How to use this script:</h3>
        <p>First create a backup zip:</p>
        <ol>
            <li>Copy this script into the root of an existing MODx installation.</li>
            <li>Call the script and run step (1 create backup).</li>
            <li>Make sure you cleaned the files (after step 1) <b>for security reasons!</b>.
                <ul>
                    <li>./export-db/</li>
                    <li>./[the zip]</li>
                    <li>./[the script]</li>
                </ul>
            </li>
        </ol>
        <p>Then install this on another server:</p>
        <ol>
            <li>Copy zip + script to the root of the new server.</li>
            <li>Call the script and run step (2, 3 and 4).</li>
            <li>Make sure you cleaned the files (after step 4) <b>for security reasons!</b>.
                <ul>
                    <li>./export-db/</li>
                    <li>./bigdump.php</li>
                    <li>./[the zip]</li>
                    <li>./[the script]</li>
                </ul>
            </li></li>
        </ol>
        <?php
        break;
}


// functions

/**
 * ZIP
 */

function my_zip($destination, $excludeFiles) {

    $excludeFiles = array_merge($excludeFiles, array(
        $destination
    ));

    if (!extension_loaded('zip')) { return false; }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) { return false; }

    addFolderToZip("./", $zip, '', $excludeFiles);
}

// Function to recursively add a directory,
// sub-directories and files to a zip archive
function addFolderToZip($dir, $zipArchive, $zipdir = '', $excludeFiles = array()){
    if (is_dir($dir) && $dh = opendir($dir)) {

        //Add the directory
        if(!empty($zipdir)) $zipArchive->addEmptyDir($zipdir);

        // Loop through all the files
        while (($file = readdir($dh)) !== false) {

            //If it's a folder, run the function again!
            if(!is_file($dir . $file)){
                // Skip parent and root directories
                if( ($file !== ".") && ($file !== "..") && !in_array($zipdir . $file, $excludeFiles)){
                    addFolderToZip($dir . $file . "/", $zipArchive, $zipdir . $file . "/", $excludeFiles);
                }
            }else{
                // Add the files (if not excluded)
                if (!in_array($zipdir . $file, $excludeFiles)) $zipArchive->addFile($dir . $file, $zipdir . $file);
            }
        }
    }
}


/**
 * Unzip
 */

function my_unzip($source, $destination) {
    $zip = new ZipArchive();
    if ($zip->open($source) && $zip->extractTo($destination)) {
        $zip->close();
        return true;
    } else {
        return false;
    }
}


function delTree($dir) {
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

function directoryToArray($directory, $recursive = true, $listDirs = false, $listFiles = true, $exclude = '') {
    $arrayItems = array();
    $skipByExclude = false;
    $handle = opendir($directory);
    if ($handle) {
        while (false !== ($file = readdir($handle))) {
            preg_match("/(^(([\.]){1,2})$|(\.(svn|git|md))|(Thumbs\.db|\.DS_STORE))$/iu", $file, $skip);
            if($exclude){
                preg_match($exclude, $file, $skipByExclude);
            }
            if (!$skip && !$skipByExclude) {
                if (is_dir($directory. DIRECTORY_SEPARATOR . $file)) {
                    if($recursive) {
                        $arrayItems = array_merge($arrayItems, directoryToArray($directory. DIRECTORY_SEPARATOR . $file, $recursive, $listDirs, $listFiles, $exclude));
                    }
                    if($listDirs){
                        $file = $directory . DIRECTORY_SEPARATOR . $file;
                        $arrayItems[] = $file;
                    }
                } else {
                    if($listFiles){
                        $file = $directory . DIRECTORY_SEPARATOR . $file;
                        $arrayItems[] = $file;
                    }
                }
            }
        }
        closedir($handle);
    }
    return $arrayItems;
}

function cURLcheckBasicFunctions()
{
    if( !function_exists("curl_init") &&
        !function_exists("curl_setopt") &&
        !function_exists("curl_exec") &&
        !function_exists("curl_close") ) return false;
    else return true;
}

/*
 * Returns string status information.
 * Can be changed to int or bool return types.
 */
function cURLdownload($url, $file)
{
    if( !cURLcheckBasicFunctions() ) return "UNAVAILABLE: cURL Basic Functions";
    $ch = curl_init();
    if($ch)
    {
        $fp = fopen($file, "w");
        if($fp)
        {
            if( !curl_setopt($ch, CURLOPT_URL, $url) )
            {
                fclose($fp); // to match fopen()
                curl_close($ch); // to match curl_init()
                return "FAIL: curl_setopt(CURLOPT_URL)";
            }
            if( !curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true) ) return "FAIL: curl_setopt(CURLOPT_FOLLOWLOCATION)";
            if( !curl_setopt($ch, CURLOPT_FILE, $fp) ) return "FAIL: curl_setopt(CURLOPT_FILE)";
            if( !curl_setopt($ch, CURLOPT_HEADER, 0) ) return "FAIL: curl_setopt(CURLOPT_HEADER)";
            if( !curl_exec($ch) ) return "FAIL: curl_exec()";
            curl_close($ch);
            fclose($fp);
            return "SUCCESS: $file [$url]";
        }
        else return "FAIL: fopen()";
    }
    else return "FAIL: curl_init()";
}

function addMessage($class, $text) {
    if (!array_key_exists("messages", $_SESSION) || !is_array($_SESSION['messages'])) $_SESSION['messages'] = array();
    $_SESSION['messages'][] = array($class, $text);
}

function flushMessages() {
    if (!array_key_exists('messages', $_SESSION)) return;
    foreach ($_SESSION['messages'] as $message) {
        list($class, $text) = $message;

        echo '<div class="alert alert-'.$class.'">'.$text.'</div>';
    }
    echo "<hr />";
    unset($_SESSION['messages']);
}

function redirectWithQuery($url, $query = array(), $permanent = false) {
    return redirect($url . "?" . http_build_query($query), $permanent);
}

function redirect($url, $permanent = false) {
    if($permanent) {
        header('HTTP/1.1 301 Moved Permanently');
    }
    header('Location: '.$url);
    exit();
}

function sQuote($input) {
    return "'$input'";
}

$content = ob_get_clean();
?>
<html>
<head>
    <meta name="robots" content="noindex, nofollow" />
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap-theme.min.css">
    <script type="text/javascript" src="http://code.jquery.com/jquery-1.10.2.min.js"></script>
</head>
<body>
<div class="container">
    <div class="page_header">
        <h2>Backup Script</h2>
        <h3>for <em>MODx Revolution</em> installations</h3>
        <?php
        $defaultclass = $zipclass = $unzipclass = $configclass = $dbimportclass = $dbimportfinishedclass = '';
        ${$action . 'class'} = 'class="active"';
        ?>
        <ul class="nav nav-pills">
            <li <?php echo $defaultclass ?>><a href="<?php echo $current_url ?>">Home</a></li>
            <li <?php echo $zipclass ?>><a href="?action=zip">1. Create Backup</a></li>
            <li <?php echo $unzipclass ?>><a href="?action=unzip">2. Extract</a></li>
            <li <?php echo $configclass ?>><a href="?action=config">3. Configure</a></li>
            <li <?php echo $dbimportclass . $dbimportfinishedclass ?>><a href="?action=dbimport">4. Import DB</a></li>
        </ul>
    </div>

    <hr />

    <?php
    flushMessages();
    echo $content;
    ?>
</div>
</body>
</html>
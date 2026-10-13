<?php
namespace App\Controllers;

use App\Models\DatabaseModel;
use PDO;
use Exception;
use Throwable;

class ApiController {
    public static function process() {
        if (empty($_SESSION['logged_in']) && (isset($_GET['action']) || isset($_POST['action']))) {
            http_response_code(401); 
            echo json_encode(['error' => 'Sesi habis, silakan reload dan login ulang!']); 
            exit;
        }

        if (!isset($_GET['action']) && !isset($_POST['action'])) return;

        ob_start();
        try {
            $pdo = null;
            $action = $_GET['action'] ?? $_POST['action'];
            
            $inputData = [];
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $contentType = $_SERVER["CONTENT_TYPE"] ?? '';
                if (strpos($contentType, 'application/json') !== false) {
                    $inputData = json_decode(file_get_contents('php://input'), true) ?? [];
                } else {
                    $inputData = $_POST;
                }
            }

            $db = DatabaseModel::safeName($_GET['db'] ?? $inputData['db'] ?? '');
            $table = DatabaseModel::safeName($_GET['table'] ?? $inputData['table'] ?? '');

            // LOGS
            if ($action === 'get_log') {
                $file = ($_GET['file'] === 'sql') ? 'sql.log' : 'login.log';
                $path = __DIR__ . '/../../' . $file;
                $content = file_exists($path) ? file_get_contents($path) : "Belum ada log tercatat.";
                ob_clean(); header('Content-Type: application/json');
                echo json_encode(['content' => $content]); exit;
            }
            if ($action === 'clear_log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $file = ($inputData['file'] === 'sql') ? 'sql.log' : 'login.log';
                $path = __DIR__ . '/../../' . $file;
                if (file_exists($path)) file_put_contents($path, '');
                ob_clean(); header('Content-Type: application/json');
                echo json_encode(['success' => true]); exit;
            }

            // INIT PDO SESSION
            $pdo = DatabaseModel::connect($_SESSION['db_host'], $_SESSION['db_port'], $_SESSION['db_user'], $_SESSION['db_pass']);

            // BACKUP / EXPORT
            if ($action === 'backup_db') {
                $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
                $reqDb = DatabaseModel::safeName($isPost ? ($_POST['db'] ?? '') : ($_GET['db'] ?? ''));
                $reqTbl = DatabaseModel::safeName($isPost ? ($_POST['table'] ?? '') : ($_GET['table'] ?? ''));
                $format = $isPost ? ($_POST['format'] ?? 'sql') : ($_GET['format'] ?? 'sql');
                
                $ids = []; $pk_col = '';
                if ($isPost) {
                    $ids = json_decode($_POST['ids'] ?? '[]', true) ?: [];
                    $pk_col = DatabaseModel::safeName($_POST['pk_col'] ?? '');
                }

                if (!$reqDb) throw new Exception("Database tidak dipilih!");
                $pdo->exec("USE `$reqDb`");
                $tables = $reqTbl ? [$reqTbl] : $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                
                ob_clean();
                if ($format === 'json') {
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="export_'.$reqDb.($reqTbl?"_$reqTbl":'').'_'.date('Ymd_His').'.json"');
                    $out = [];
                    foreach($tables as $t) { 
                        if (!empty($ids) && $t === $reqTbl && $pk_col) {
                            $in = implode(',', array_fill(0, count($ids), '?'));
                            $stmt = $pdo->prepare("SELECT * FROM `$t` WHERE `$pk_col` IN ($in)");
                            $stmt->execute($ids);
                            $out[$t] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } else {
                            $out[$t] = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC); 
                        }
                    }
                    echo json_encode(count($tables) === 1 ? $out[$reqTbl] : $out, JSON_PRETTY_PRINT);
                } elseif ($format === 'csv') {
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="export_'.$reqDb.($reqTbl?"_$reqTbl":'').'_'.date('Ymd_His').'.csv"');
                    $out = fopen('php://output', 'w');
                    foreach($tables as $t) {
                        if (!empty($ids) && $t === $reqTbl && $pk_col) {
                            $in = implode(',', array_fill(0, count($ids), '?'));
                            $stmt = $pdo->prepare("SELECT * FROM `$t` WHERE `$pk_col` IN ($in)");
                            $stmt->execute($ids);
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } else {
                            $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
                        }
                        if (!empty($rows)) {
                            fputcsv($out, array_keys($rows[0]));
                            foreach($rows as $r) fputcsv($out, $r);
                        }
                        if (count($tables) > 1) fputcsv($out, []);
                    }
                    fclose($out);
                } else {
                    header('Content-Type: text/plain');
                    header('Content-Disposition: attachment; filename="backup_'.$reqDb.($reqTbl?"_$reqTbl":'').'_'.date('Ymd_His').'.sql"');
                    $out = "-- Backup Database: $reqDb\n-- Waktu: " . date('Y-m-d H:i:s') . "\n\n";
                    foreach($tables as $t) {
                        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
                        $out .=$create . ";\n\n";
                        
                        if (!empty($ids) &&$t === $reqTbl &&$pk_col) {
                            $in = implode(',', array_fill(0, count($ids), '?'));
                            $stmt =$pdo->prepare("SELECT * FROM `$t` WHERE `$pk_col` IN ($in)");
                            $stmt->execute($ids);
                            $rows =$stmt->fetchAll(PDO::FETCH_NUM);
                        } else {
                            $rows =$pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_NUM);
                        }
                        
                        foreach($rows as$r) {
                            $vals = array_map(function($v) use($pdo) { return$v === null ? 'NULL' : $pdo->quote($v); }, $r);$out .= "INSERT INTO `$t` VALUES (" . implode(',', $vals) . ");\n";
                        }
                        $out .= "\n\n";
                    }
                    echo $out;
                }
                DatabaseModel::writeLog('sql.log', "EXPORT DB: '$reqDb' FORMAT: $format" . (!empty($ids) ? " (BULK)" : "")); exit;
            }

            header('Content-Type: application/json');

            // MONITOR
            if ($action === 'monitor') {
                $procs =$pdo->query("SHOW PROCESSLIST")->fetchAll();
                ob_clean(); echo json_encode(['processes' => $procs]); exit;
            }
            if ($action === 'kill_process' &&$_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = intval($inputData['id']);
                $pdo->exec("KILL $id");
                DatabaseModel::writeLog('sql.log', "SERVER MONITOR: Killed Process ID $id");
                ob_clean(); echo json_encode(['success' => true]); exit;
            }

            if ($db)$pdo->exec("USE `$db`");

            if ($action === 'databases') { ob_clean(); echo json_encode($pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN)); exit; }
            if ($action === 'tables') { ob_clean(); echo json_encode($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN)); exit; }
            if ($action === 'structure') { ob_clean(); echo json_encode($pdo->query("DESCRIBE `$table`")->fetchAll()); exit; }

            // DDL MANAGEMENT
            if ($action === 'create_table') {
                $tbl = DatabaseModel::safeName($inputData['name']);
                $cols =$inputData['cols'];
                if(empty($cols)) throw new Exception("Minimal 1 kolom diperlukan!");
                $sql = "CREATE TABLE `$tbl` (";
                $defs = [];$pk = '';
                foreach ($cols as $c) {$cn = DatabaseModel::safeName($c['name']);$type = preg_replace('/[^a-zA-Z0-9]/', '', $c['type']);$len = intval($c['len']);$def = "`$cn` $type" . ($len > 0 ? "($len)" : "");
                    if (!empty($c['ai']))$def .= " AUTO_INCREMENT";
                    if (!empty($c['pk'])) $pk =$cn;
                    $defs[] =$def;
                }
                if ($pk)$defs[] = "PRIMARY KEY (`$pk`)";
                $sql .= implode(', ',$defs) . ")";
                $pdo->exec($sql);
                DatabaseModel::writeLog('sql.log', "DB: $db \vert{} Create Table baru:$tbl");
                ob_clean(); echo json_encode(['success' => true]); exit;
            }
            if ($action === 'drop_table') {$pdo->exec("DROP TABLE `$table`"); DatabaseModel::writeLog('sql.log', "DB: $db \vert{} DROP TABLE:$table"); ob_clean(); echo json_encode(['success' => true]); exit; }
            if ($action === 'truncate_table') {$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                $pdo->exec("TRUNCATE TABLE `$table`"); 
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                DatabaseModel::writeLog('sql.log', "DB: $db \vert{} TRUNCATE TABLE:$table"); 
                ob_clean(); echo json_encode(['success' => true]); exit; 
            }
            if ($action === 'rename_table') {$new = DatabaseModel::safeName($inputData['new_name']);$pdo->exec("RENAME TABLE `$table` TO `$new`"); DatabaseModel::writeLog('sql.log', "DB: $db | RENAME TABLE: $table ->$new"); ob_clean(); echo json_encode(['success' => true]); exit; }

            if ($action === 'data') {
                $columns =$pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
                if (empty($columns)) { ob_clean(); echo json_encode(['columns' => [], 'data' => [], 'pk' => null]); exit; }
                $pk =$pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = 'PRIMARY'")->fetchColumn(4) ?: null;
                $data =$pdo->query("SELECT * FROM `$table`")->fetchAll();
                ob_clean(); echo json_encode(['columns' => $columns, 'data' => $data, 'pk' =>$pk]); exit;
            }

            // FAKER GENERATOR
            if ($action === 'generate_dummy') {
                $rows = intval($inputData['rows'] ?? 10);
                $cols =$pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll();
                $count = 0;
                $pdo->beginTransaction();
                for ($i=0; $i<$rows; $i++) {
                    $insertCols = [];$insertVals = [];
                    foreach ($cols as$c) {
                        $extra = strtolower($c['Extra']);
                        if (strpos($extra, 'auto_increment') !== false || strpos($extra, 'generated') !== false || strpos($extra, 'virtual') !== false) { continue; }$insertCols[] = str_replace('`', '``', $c['Field']);$type = strtolower($c['Type']);$field = strtolower($c['Field']);$val = "Data_" . uniqid() . "_" . rand(1, 100);
                        
                        if (strpos($type, 'tinyint') !== false || strpos($type, 'bool') !== false)$val = rand(0, 1);
                        elseif (strpos($type, 'int') !== false || strpos($type, 'bigint') !== false)$val = rand(1, 999999);
                        elseif (strpos($type, 'float') !== false || strpos($type, 'decimal') !== false || strpos($type, 'double') !== false)$val = rand(100, 9999) / 100;
                        elseif (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false)$val = date('Y-m-d H:i:s', time() - rand(0, 31536000));
                        elseif (strpos($type, 'date') !== false)$val = date('Y-m-d', time() - rand(0, 31536000));
                        elseif (strpos($type, 'time') !== false)$val = date('H:i:s', time() - rand(0, 86400));
                        elseif (strpos($field, 'email') !== false)$val = "user_" . uniqid() . "@faker.com";
                        elseif (strpos($field, 'name') !== false || strpos($field, 'nama') !== false || strpos($field, 'user') !== false)$val = "User_" . uniqid();
                        elseif (strpos($field, 'phone') !== false || strpos($field, 'telp') !== false)$val = "08" . rand(100000000, 999999999);
                        
                        $insertVals[] =$val;
                    }
                    if (empty($insertCols)) throw new Exception("Tabel ini tidak memiliki kolom yang dapat diisi!");
                    $placeholders = implode(',', array_fill(0, count($insertVals), '?'));$colNames = implode('`,`', $insertCols);$sql = "INSERT INTO `$table` (`$colNames`) VALUES ($placeholders)";
                    try {
                        $stmt =$pdo->prepare($sql);$stmt->execute($insertVals);$count++;
                    } catch (Exception $e) {$pdo->rollBack();
                        throw new Exception("Crash MySQL! " . $e->getMessage());
                    }
                }
                $pdo->commit();
                DatabaseModel::writeLog('sql.log', "DB: $db | DUMMY FAKER: Generate $count baris di$table");
                ob_clean(); echo json_encode(['success' => true, 'count' => $count]); exit;
            }

            // IMPORT DATA
            if ($action === 'import_data' &&$_SERVER['REQUEST_METHOD'] === 'POST') {
                set_time_limit(0); ignore_user_abort(true); ini_set('memory_limit', '-1');
                if (!isset($_FILES['file']) ||$_FILES['file']['error'] !== UPLOAD_ERR_OK) throw new Exception("Gagal upload file!");
                
                $filename =$_FILES['file']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if ($ext === 'sql') {$tmpFilePath = $_FILES['file']['tmp_name'];$persistentFile = __DIR__ . '/../../import_' . uniqid() . '.sql';
                    move_uploaded_file($tmpFilePath,$persistentFile);

                    $command = sprintf(
                        'MYSQL_PWD=%s mysql -h%s -P%s -u%s %s < %s && rm %s > /dev/null 2>&1 &',
                        escapeshellarg($_SESSION['db_pass']),
                        escapeshellarg($_SESSION['db_host']),
                        escapeshellarg($_SESSION['db_port']),
                        escapeshellarg($_SESSION['db_user']),
                        escapeshellarg($db),
                        escapeshellarg($persistentFile),
                        escapeshellarg($persistentFile)
                    );
                    exec($command);
                    DatabaseModel::writeLog('sql.log', "DB: $db \vert{} Import SQL started:$filename");
                    ob_clean(); echo json_encode(['success' => true, 'msg' => 'Import SQL berjalan di background!']); exit;
                } elseif ($ext === 'csv') {
                    if (!$table) throw new Exception("Pilih tabel dulu!");
                    $handle = fopen($_FILES['file']['tmp_name'], "r");
                    $header = fgetcsv($handle);
                    if (!$header) throw new Exception("CSV Kosong!");
                    $header = array_map([DatabaseModel::class, 'safeName'],$header);
                    $pdo->beginTransaction();$placeholders = implode(',', array_fill(0, count($header), '?'));$colNames = implode('`,`', $header);
                    $stmt =$pdo->prepare("INSERT INTO `$table` (`$colNames`) VALUES ($placeholders)");
                    $count = 0;
                    while (($row = fgetcsv($handle)) !== FALSE) {
                        $row = array_pad(array_slice($row, 0, count($header)), count($header), null);
                        $row = array_map(function($v){ return $v === '' ? null : $v; }, $row);$stmt->execute($row);$count++;
                    }
                    $pdo->commit(); fclose($handle);
                    DatabaseModel::writeLog('sql.log', "DB: $db | Import CSV ke $table ($count baris)");
                    ob_clean(); echo json_encode(['success' => true, 'msg' => "$count baris di-import!"]); exit;
                } else {
                    throw new Exception("Format tidak didukung!");
                }
            }
            
            // CRUD & BULK
            if ($action === 'update' &&$_SERVER['REQUEST_METHOD'] === 'POST') {
                $col = DatabaseModel::safeName($inputData['col'] ?? ''); $pk_col = DatabaseModel::safeName($inputData['pk_col'] ?? '');
                if (empty($pk_col)) throw new Exception("Tabel tidak memiliki Primary Key!");
                $stmt =$pdo->prepare("UPDATE `$table` SET `$col` = ? WHERE `$pk_col` = ?");
                $stmt->execute([$inputData['val'],$inputData['pk_val']]);
                DatabaseModel::writeLog('sql.log', "DB: $db | UPDATE $table ($col)");
                ob_clean(); echo json_encode(['success' => true]); exit;
            }

            if ($action === 'delete' &&$_SERVER['REQUEST_METHOD'] === 'POST') {
                $pk_col = DatabaseModel::safeName($inputData['pk_col'] ?? '');
                $stmt =$pdo->prepare("DELETE FROM `$table` WHERE `$pk_col` = ?");
                $stmt->execute([$inputData['pk_val']]);
                DatabaseModel::writeLog('sql.log', "DB: $db \vert{} DELETE row di$table");
                ob_clean(); echo json_encode(['success' => true]); exit;
            }
            
            if ($action === 'bulk_delete' &&$_SERVER['REQUEST_METHOD'] === 'POST') {
                $pk_col = DatabaseModel::safeName($inputData['pk_col'] ?? ''); $ids =$inputData['ids'] ?? [];
                if(empty($ids)) throw new Exception("Tidak ada data dipilih!");
                $inQuery = implode(',', array_fill(0, count($ids), '?'));
                $stmt =$pdo->prepare("DELETE FROM `$table` WHERE `$pk_col` IN ($inQuery)");
                $stmt->execute($ids);
                DatabaseModel::writeLog('sql.log', "DB: $db | BULK DELETE di $table (" . count($ids) . ")");
                ob_clean(); echo json_encode(['success' => true]); exit;
            }

            if ($action === 'bulk_copy' &&$_SERVER['REQUEST_METHOD'] === 'POST') {
                $pk_col = DatabaseModel::safeName($inputData['pk_col'] ?? ''); $ids =$inputData['ids'] ?? [];
                if(empty($ids) || empty($pk_col)) throw new Exception("Data/PK tidak valid!");
                $inQuery = implode(',', array_fill(0, count($ids), '?'));
                $colsMeta =$pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll();
                $insertCols = [];
                foreach ($colsMeta as$c) {
                    $field =$c['Field']; $extra = strtolower($c['Extra']);
                    if (strtolower($field) === $pk_col && strpos($extra, 'auto_increment') !== false) continue;
                    $insertCols[] =$field;
                }
                $stmtGet =$pdo->prepare("SELECT * FROM `$table` WHERE `$pk_col` IN ($inQuery)");
                $stmtGet->execute($ids);
                $rows =$stmtGet->fetchAll(PDO::FETCH_ASSOC);

                $pdo->beginTransaction();$placeholders = implode(',', array_fill(0, count($insertCols), '?'));$colNamesQuoted = implode('`,`', $insertCols);
                $stmtInsert =$pdo->prepare("INSERT INTO `$table` (`$colNamesQuoted`) VALUES ($placeholders)");
                $count = 0;
                foreach ($rows as $row) {$vals = [];
                    foreach ($insertCols as$col) { $vals[] =$row[$col]; }$stmtInsert->execute($vals);$count++;
                }
                $pdo->commit();
                DatabaseModel::writeLog('sql.log', "DB: $db | BULK COPY di $table ($count)");
                ob_clean(); echo json_encode(['success' => true, 'count' => $count]); exit;
            }

            if ($action === 'insert' && $_SERVER['REQUEST_METHOD'] === 'POST') {$insertData = $inputData['data'] ?? [];$cols = array_map([DatabaseModel::class, 'safeName'], array_keys($insertData));$vals = array_values($insertData);$placeholders = implode(',', array_fill(0, count($vals), '?'));$colNames = implode('`,`', $cols);
                $stmt =$pdo->prepare("INSERT INTO `$table` (`$colNames`) VALUES ($placeholders)");
                $stmt->execute($vals);
                DatabaseModel::writeLog('sql.log', "DB: $db \vert{} INSERT ke$table");
                ob_clean(); echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]); exit;
            }

            if ($action === 'query' &&$_SERVER['REQUEST_METHOD'] === 'POST') {
                $sql = trim($inputData['sql'] ?? '');
                DatabaseModel::writeLog('sql.log', "DB: $db \vert{} RAW QUERY:$sql");
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
                $stmt =$pdo->prepare($sql);$stmt->execute();
                if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)/i', $sql)) {$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);$cols = empty($rows) ? [] : array_keys($rows[0]);
                    ob_clean(); echo json_encode(['type' => 'select', 'columns' => $cols, 'data' =>$rows]);
                } else {
                    ob_clean(); echo json_encode(['type' => 'modify', 'affected' => $stmt->rowCount()]);
                } exit;
            }
        } catch (Throwable $e) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json');
            http_response_code(500); echo json_encode(['error' => $e->getMessage()]); exit;
        }
    }
}
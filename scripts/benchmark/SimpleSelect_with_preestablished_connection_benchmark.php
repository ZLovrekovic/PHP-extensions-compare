<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "classicmodels";

// Funkcija za logovanje svih rezultata (po iteraciji)
function logResult($method, $time, $memoryPeak) {
    $line = "$method,$time,$memoryPeak\n";
    file_put_contents("rezultat.csv", $line, FILE_APPEND);
}

// Funkcija za logovanje proseka
function logResult2($method, $time, $memoryPeak) {
    $line = "$method,$time,$memoryPeak\n";
    file_put_contents("rezultatsrv.csv", $line, FILE_APPEND);
}

// Glavna benchmark funkcija
function benchmark(callable $fn, string $method, int $runs = 200, ...$args) {
    $times = [];
    $memoriesPeak = [];

    for ($i = 0; $i < $runs; $i++) {
        gc_collect_cycles();

        $start = hrtime(true);
        $startMem = memory_get_usage(false);

        $fn(...$args);

        $time = (hrtime(true) - $start) / 1e6; // ms
        $peakMem = (memory_get_peak_usage(false) - $startMem) /1024 ; //KB,  za MB deli sa 1048576

        $times[] = $time;
        $memoriesPeak[] = $peakMem;
        logResult($method, $time, $peakMem);
    }

    $avgTime = round(array_sum($times) / count($times), 3);
    $avgPeakMem = round(array_sum($memoriesPeak) / count($memoriesPeak), 3);

    logResult2($method, $avgTime, $avgPeakMem);
}

// Inicijalizuj CSV fajlove
file_put_contents("rezultat.csv", "\xEF\xBB\xBFmethod,time_ms,memory_peak_kb\n");
file_put_contents("rezultatsrv.csv", "\xEF\xBB\xBFmethod,time_ms,memory_peak_kb\n");

// SQL upiti
	
	$status='Shipped';

$query1 = "
    SELECT orderNumber FROM orders WHERE status = '$status'
    LIMIT 1000
";
$query2 = "
    SELECT orderNumber FROM orders WHERE status = ?
    LIMIT 1000
";
$query3 = "
    SELECT orderNumber FROM orders WHERE status = :status
    LIMIT 1000
";
// 1. MySQLi procedural – konekcija van benchmark
$conn1 = mysqli_connect($host, $user, $pass, $db);
if (!$conn1) die("MySQLi procedural - greška konekcije: " . mysqli_connect_error());

benchmark(function($conn, $query) {
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {}
    mysqli_free_result($result);
}, "mysqli_procedural_static", 200, $conn1, $query1);

mysqli_close($conn1);

// 2. MySQLi object
$conn2 = new mysqli($host, $user, $pass, $db);
if ($conn2->connect_error) die("MySQLi object - greška konekcije: " . $conn2->connect_error);

benchmark(function($conn, $query) {
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {}
    $result->free();
}, "mysqli_object_static", 200, $conn2, $query1);

$conn2->close();

// 3. MySQLi prepared
$conn3 = new mysqli($host, $user, $pass, $db);
if ($conn3->connect_error) die("MySQLi prepared - greška konekcije: " . $conn3->connect_error);

$stmt3 = $conn3->prepare($query2);
if (!$stmt3) die("MySQLi prepared - greška pripreme: " . $conn3->error);
$stmt3->bind_param("s", $status);

benchmark(function($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {}
    $result->free();
}, "mysqli_prepared_static", 200, $stmt3);

$stmt3->close();
$conn3->close();

// 4. PDO query
$pdo1 = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
$pdo1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

benchmark(function($pdo, $query) {
    $stmt = $pdo->query($query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {}
}, "pdo_query_static", 200, $pdo1, $query1);

$pdo1 = null;

// 5. PDO prepared native
$pdo2 = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
$pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo2->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // false -isključuje emulaciju - koristi prave prepared statements

$stmt2 = $pdo2->prepare($query3);
$stmt2->bindParam(':status', $status, PDO::PARAM_STR);

benchmark(function($stmt) {
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {}
	$stmt->closeCursor();
}, "pdo_prepared_static_n", 200, $stmt2);

$stmt2 = null;
$pdo2 = null;
// 6. PDO prepared emulated
$pdo3 = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
$pdo3->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo3->setAttribute(PDO::ATTR_EMULATE_PREPARES, true); // true -uključuje emulaciju - koristi emulirane prepared statements

$stmt3 = $pdo3->prepare($query3);
$stmt3->bindParam(':status', $status, PDO::PARAM_STR);

benchmark(function($stmt) {
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {}
	$stmt->closeCursor();
}, "pdo_prepared_static_e", 200, $stmt3);

$stmt3 = null;
$pdo3 = null;
echo "Završeno. Rezultati su u fajlovima rezultat.csv i rezultatsrv.csv\n";
?>

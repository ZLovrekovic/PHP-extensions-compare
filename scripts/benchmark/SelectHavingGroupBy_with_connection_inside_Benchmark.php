<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "classicmodels";

// Funkcija za logovanje rezultata u CSV
function logResult($method, $time, $memoryPeak) {
    $line = "$method,$time,$memoryPeak\n";
    file_put_contents("rezultat.csv", $line, FILE_APPEND);
}
function logResult2($method, $time, $memoryPeak) {
    $line = "$method,$time,$memoryPeak\n";
    file_put_contents("rezultatsrv.csv", $line, FILE_APPEND);
}
// Funkcija za pokretanje benchmark testa
function benchmark(callable $fn, string $method, int $runs = 200) {
    $times = [];
    $memoriesPeak = [];

    for ($i = 0; $i < $runs; $i++) {
        gc_collect_cycles(); // preporučeno pre merenja memorije

        $start = hrtime(true);
        $startMem = memory_get_usage(false);

        $fn();

        $time = (hrtime(true) - $start) / 1e6; // ms
        $peakMem = (memory_get_peak_usage(false) - $startMem) /1024 ; //KB, za MB deli sa 1048576

        $times[] = $time;
        $memoriesPeak[] = $peakMem;
		logResult($method, $time, $peakMem);
    }

    // Računaj proseke
    $avgTime = round(array_sum($times) / count($times), 3);
    $avgPeakMem = round(array_sum($memoriesPeak) / count($memoriesPeak), 3);

    logResult2($method, $avgTime, $avgPeakMem);
}

// Kreiraj CSV fajl sa BOM i zaglavljem
file_put_contents("rezultat.csv", "\xEF\xBB\xBFmethod,time_ms,memory_peak_kb\n");
file_put_contents("rezultatsrv.csv", "\xEF\xBB\xBFmethod,time_ms,memory_peak_kb\n");
// SQL upit
$productLine = "Classic Cars";
$minAvg = 50;
$query1 = "
    SELECT 
    p.productName,
    AVG(od.quantityOrdered) AS avgQuantity,
    COUNT(*) AS brojPorudzbina
	FROM orderdetails od
	JOIN products p ON od.productCode = p.productCode
	WHERE p.productLine = '$productLine'
	GROUP BY p.productName
	HAVING avgQuantity > '$minAvg'
	LIMIT 10000
";
$query2 = "
    SELECT 
    p.productName,
    AVG(od.quantityOrdered) AS avgQuantity,
    COUNT(*) AS brojPorudzbina
	FROM orderdetails od
	JOIN products p ON od.productCode = p.productCode
	WHERE p.productLine = ?
	GROUP BY p.productName
	HAVING avgQuantity > ?
	LIMIT 10000
";
$query3 = "
    SELECT 
    p.productName,
    AVG(od.quantityOrdered) AS avgQuantity,
    COUNT(*) AS brojPorudzbina
FROM orderdetails od
JOIN products p ON od.productCode = p.productCode
WHERE p.productLine = :productLine
GROUP BY p.productName
HAVING avgQuantity > :minAvg
LIMIT 10000
";
// 1. MySQLi procedural
benchmark(function() use ($host, $user, $pass, $db, $query1, $productLine, $minAvg) {
    $conn = mysqli_connect($host, $user, $pass, $db);
    if (!$conn) die("MySQLi (procedural) - greška konekcije: " . mysqli_connect_error());

    $result = mysqli_query($conn, $query1);
    if (!$result) die("MySQLi (procedural) - greška upita: " . mysqli_error($conn));
    while ($row = mysqli_fetch_assoc($result)) {}
    mysqli_free_result($result);
    mysqli_close($conn);
}, "mysqli_procedural");

// 2. MySQLi object
benchmark(function() use ($host, $user, $pass, $db, $query1, $productLine, $minAvg) {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) die("MySQLi (object) - greška konekcije: " . $conn->connect_error);
    $result = $conn->query($query1);
    if (!$result) die("MySQLi (object) - greška upita: " . $conn->error);

    while ($row = $result->fetch_assoc()) {}
    $result->free();
    $conn->close();
}, "mysqli_object");

// 3. MySQLi prepared statement
benchmark(function() use ($host, $user, $pass, $db, $query2, $productLine, $minAvg) {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) die("MySQLi (prepared) - greška konekcije: " . $conn->connect_error);
    $stmt = $conn->prepare($query2);
    if (!$stmt) die("MySQLi (prepared) - greška pripreme: " . $conn->error);
	$stmt->bind_param("si", $productLine, $minAvg);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {}
    $result->free();
    $stmt->close();
    $conn->close();
}, "mysqli_prepared");

// 4. PDO query
benchmark(function() use ($host, $user, $pass, $db, $query1, $productLine, $minAvg) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach ($pdo->query($query1) as $row) {}
        $pdo = null;
    } catch (PDOException $e) {
        die("PDO (query) - greška: " . $e->getMessage());
    }
}, "pdo_query");

// 5. PDO prepared emulated
benchmark(function() use ($host, $user, $pass, $db, $query3, $productLine, $minAvg) {
	
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true); // true -uključuje emulaciju - koristi emulirane prepared statements
        $stmt = $pdo->prepare($query3);
		$stmt->bindParam(':productLine', $productLine, PDO::PARAM_STR);
		$stmt->bindParam(':minAvg', $minAvg, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {}
        $stmt = null;
        $pdo = null;
    } catch (PDOException $e) {
        die("PDO (prepared) - greška: " . $e->getMessage());
    }
}, "pdo_prepared_e");
// 6. PDO prepared native
benchmark(function() use ($host, $user, $pass, $db, $query3, $productLine, $minAvg) {
	
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // false -isključuje emulaciju - koristi native prepared statements
        $stmt = $pdo->prepare($query3);
		$stmt->bindParam(':productLine', $productLine, PDO::PARAM_STR);
		$stmt->bindParam(':minAvg', $minAvg, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {}
        $stmt = null;
        $pdo = null;
    } catch (PDOException $e) {
        die("PDO (prepared) - greška: " . $e->getMessage());
    }
}, "pdo_prepared_n");

echo "Završeno. Rezultati su upisani u fajl rezultat.csv i rezultatsrv.csv\n";
?>

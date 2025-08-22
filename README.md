# PHP Benchmark for Database Access Methods

## 3.1 Experimental Environment Description
The experiments were performed on a local workstation with the following hardware and software specifications:
- Operating System: Windows 10 Home 22H2 (64-bit)
- WAMP Environment: WampServer 3.3.0 (64-bit), including:
  - Apache 2.4.54.2
  - MySQL 8.0.31
  - PHP 8.0.26
  - phpMyAdmin 5.2
- Hardware: Intel Core i7-2630QM, 12 GB RAM, 120 GB SSD

The ClassicModels database, obtained from www.mysqltutorial.org, was used, providing a realistic dataset for testing SQL queries of various complexities.

## 3.2 Defined Query Tests
Testing was conducted for the following PHP database access methods:
- MySQLi (procedural)
- MySQLi (object-oriented)
- MySQLi prepared statements
- PDO (object-oriented)
- PDO prepared statements (emulated)
- PDO prepared statements (native)

For each method, the following SQL queries were analyzed, executed in three variants (classic, positional, and named prepared statements):

| Label | Query Type                         | Number of JOINs | Clauses              |
|-------|----------------------------------|-----------------|----------------------|
| Q1    | Simple SELECT with WHERE and LIMIT | 0               | WHERE, LIMIT         |
| Q2    | SELECT with 5 JOINs               | 5               | WHERE, LIMIT         |
| Q3    | SELECT with 2 JOINs, GROUP BY, and HAVING | 2               | WHERE, GROUP BY, HAVING |

Two benchmark variants were tested for each query:
- Full benchmark: includes connection setup.
- Isolated execution: measures only the execution of a previously prepared query.

## 3.3 Performance Metrics
The primary performance metrics were:
- Query execution time in milliseconds (ms).
- Peak memory usage under load in kilobytes (KB).

CPU time was initially considered as an additional metric; however, the values were highly unstable — most executions recorded 0 µs, with sporadic anomalies (e.g., 15625 µs), without consistent correlation to method or query complexity. Therefore, CPU time was discarded as an unreliable metric in this experimental context, likely due to interference from parallel system processes.

The focus was thus maintained on execution time and memory efficiency, as the most relevant indicators for real-world PHP/MySQL applications.

## 3.4 Test Implementation Description
Benchmark measurements were performed using a dedicated PHP function `benchmark()`, which allows multiple (n=200) executions of the tested code and logs the time and memory usage. The function accepts an anonymous callable representing the specific test.

Benchmark function:

```php
function benchmark(callable $fn, string $method, int $runs = 200) {
    $times = [];
    $memoriesPeak = [];

    for ($i = 0; $i < $runs; $i++) {
        gc_collect_cycles();
        $start = hrtime(true);
        $startMem = memory_get_usage(false);

        $fn();

        $time = (hrtime(true) - $start) / 1e6; // ms
        $peakMem = (memory_get_peak_usage(false) - $startMem) / 1024; // KB

        $times[] = $time;
        $memoriesPeak[] = $peakMem;
        logResult($method, $time, $peakMem);
    }

    $avgTime = round(array_sum($times) / count($times), 3);
    $avgPeakMem = round(array_sum($memoriesPeak) / count($memoriesPeak), 3);
    logResult2($method, $avgTime, $avgPeakMem);
}

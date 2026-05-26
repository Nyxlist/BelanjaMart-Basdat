<?php
/**
 * Database - very small PDO singleton with prepared-statement helpers.
 *
 * All other code MUST go through this class instead of using the raw
 * mysqli_* functions used by the original project.
 */

class Database
{
    private static ?PDO $pdo = null;

    /** Get the shared PDO connection (creates it on first call). */
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg  = config('db');
        $dsn  = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // In a real app we'd render a friendlier page here.
            http_response_code(500);
            die('Database connection failed: ' . $e->getMessage());
        }
        return self::$pdo;
    }

    /** Run a prepared query and return the statement. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch a single row (or null). */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row ?: null;
    }

    /** Fetch all rows. */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** Fetch a single scalar value (first column of first row). */
    public static function scalar(string $sql, array $params = [])
    {
        $stmt = self::run($sql, $params);
        $row  = $stmt->fetch(PDO::FETCH_NUM);
        return $row ? $row[0] : null;
    }

    /** Insert helper.  Returns last insert id. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(',', $cols),
            implode(',', array_fill(0, count($cols), '?'))
        );
        self::run($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    /** Update helper.  Where is "col = ? AND col2 = ?". */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
        $sql = "UPDATE $table SET $set WHERE $where";
        return self::run($sql, array_merge(array_values($data), $whereParams))->rowCount();
    }

    public static function delete(string $table, string $where, array $whereParams = []): int
    {
        return self::run("DELETE FROM $table WHERE $where", $whereParams)->rowCount();
    }

    /** Run callable inside a DB transaction. */
    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

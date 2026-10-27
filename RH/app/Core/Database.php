<?php
declare(strict_types=1);
final class Database {
    public PDO $pdo;
    public string $driver;
    public function __construct(public array $config){
        $this->driver=$config['driver'];
        if($this->driver==='sqlite'){
            if(!is_dir(dirname($config['path'])))mkdir(dirname($config['path']),0700,true);
            $dsn='sqlite:'.$config['path'];
        }elseif($this->driver==='mysql'){
            if(!$config['user'])throw new RuntimeException('Configure DB_USER, DB_PASS e DB_NAME no ambiente do servidor.');
            $dsn="mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
        }else throw new RuntimeException('Banco não suportado.');
        $this->pdo=new PDO($dsn,$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        if($this->driver==='sqlite'){$this->pdo->exec('PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000');}
    }
    public function all(string $sql,array $args=[]):array{$q=$this->pdo->prepare($sql);$q->execute($args);return $q->fetchAll();}
    public function one(string $sql,array $args=[]):?array{return $this->all($sql,$args)[0]??null;}
    public function run(string $sql,array $args=[]):int{$q=$this->pdo->prepare($sql);$q->execute($args);return $q->rowCount();}
    public function id():int{return (int)$this->pdo->lastInsertId();}
    public function transaction(callable $f):mixed{$this->pdo->beginTransaction();try{$r=$f();$this->pdo->commit();return $r;}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}}
}

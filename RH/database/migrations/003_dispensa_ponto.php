<?php
declare(strict_types=1);
return ['name'=>'Dispensa de ponto e horário de referência','up'=>static function(PDO $pdo,string $driver):void{
    $columns=$driver==='mysql'?$pdo->query('SHOW COLUMNS FROM employees')->fetchAll(PDO::FETCH_ASSOC):$pdo->query('PRAGMA table_info(employees)')->fetchAll(PDO::FETCH_ASSOC);
    if(!in_array('punch_exempt',array_column($columns,$driver==='mysql'?'Field':'name'),true))$pdo->exec('ALTER TABLE employees ADD COLUMN punch_exempt INTEGER NOT NULL DEFAULT 0');
}];

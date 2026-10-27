<?php
return ['name'=>'Estrutura inicial do RH em PHP', 'up'=>function(PDO $db,string $driver): void {
    $id=$driver==='mysql'?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $queries=[
        "CREATE TABLE IF NOT EXISTS sectors(id $id,name VARCHAR(180) NOT NULL UNIQUE,responsible VARCHAR(180) NOT NULL DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS employees(id $id,name VARCHAR(180) NOT NULL,cpf VARCHAR(11) NULL UNIQUE,email VARCHAR(240) NOT NULL DEFAULT '',role VARCHAR(180) NOT NULL DEFAULT '',sector_id INTEGER NULL,admission VARCHAR(10) NULL,status VARCHAR(30) NOT NULL DEFAULT 'Ativo',phone VARCHAR(30) NOT NULL DEFAULT '',notes TEXT NOT NULL,FOREIGN KEY(sector_id) REFERENCES sectors(id))",
        "CREATE TABLE IF NOT EXISTS users(id $id,name VARCHAR(180) NOT NULL,login VARCHAR(180) NOT NULL UNIQUE,password VARCHAR(255) NOT NULL,role VARCHAR(30) NOT NULL,employee_id INTEGER NULL UNIQUE,active INTEGER NOT NULL DEFAULT 1,session_version INTEGER NOT NULL DEFAULT 0,FOREIGN KEY(employee_id) REFERENCES employees(id))",
        "CREATE TABLE IF NOT EXISTS schedules(id $id,employee_id INTEGER NOT NULL UNIQUE,days VARCHAR(40) NOT NULL,entry VARCHAR(5) NOT NULL,break_start VARCHAR(5) NOT NULL,break_end VARCHAR(5) NOT NULL,departure VARCHAR(5) NOT NULL,FOREIGN KEY(employee_id) REFERENCES employees(id))",
        "CREATE TABLE IF NOT EXISTS punches(id $id,employee_id INTEGER NOT NULL,kind VARCHAR(40) NOT NULL,at VARCHAR(30) NOT NULL,actor INTEGER NOT NULL,FOREIGN KEY(employee_id) REFERENCES employees(id),FOREIGN KEY(actor) REFERENCES users(id))",
        "CREATE TABLE IF NOT EXISTS leaves(id $id,employee_id INTEGER NOT NULL,kind VARCHAR(30) NOT NULL,start VARCHAR(10) NOT NULL,end VARCHAR(10) NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'Pendente',notes TEXT NOT NULL,review TEXT NOT NULL,FOREIGN KEY(employee_id) REFERENCES employees(id))",
        "CREATE TABLE IF NOT EXISTS documents(id $id,employee_id INTEGER NOT NULL,category VARCHAR(80) NOT NULL,start VARCHAR(10) NOT NULL,end VARCHAR(10) NOT NULL,notes TEXT NOT NULL,original_name VARCHAR(180) NOT NULL,stored_name VARCHAR(80) NOT NULL,mime VARCHAR(80) NOT NULL,status VARCHAR(40) NOT NULL DEFAULT 'Recebido',review TEXT NOT NULL,created VARCHAR(30) NOT NULL,FOREIGN KEY(employee_id) REFERENCES employees(id))",
        "CREATE TABLE IF NOT EXISTS audit(id $id,actor VARCHAR(180) NOT NULL,action VARCHAR(180) NOT NULL,detail TEXT NOT NULL,at VARCHAR(30) NOT NULL)",
        "CREATE TABLE IF NOT EXISTS login_attempts(attempt_key VARCHAR(64) PRIMARY KEY,n INTEGER NOT NULL,expires BIGINT NOT NULL)",
        "CREATE TABLE IF NOT EXISTS system_settings(setting_key VARCHAR(80) PRIMARY KEY,setting_value TEXT NOT NULL)"
    ];
    foreach($queries as $sql)$db->exec($sql.($driver==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':''));
}];

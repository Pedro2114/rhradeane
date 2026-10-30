<?php
return ['name'=>'Checklist e links privados para envio de documentos', 'up'=>function(PDO $db,string $driver):void {
    $id=$driver==='mysql'?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';$suffix=$driver==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    $db->exec("CREATE TABLE IF NOT EXISTS document_requests(id $id,employee_id INTEGER NOT NULL,token_hash VARCHAR(64) NOT NULL UNIQUE,expires VARCHAR(10) NOT NULL,active INTEGER NOT NULL DEFAULT 1,created VARCHAR(30) NOT NULL,FOREIGN KEY(employee_id) REFERENCES employees(id))".$suffix);
    $db->exec("CREATE TABLE IF NOT EXISTS document_request_items(id $id,request_id INTEGER NOT NULL,title VARCHAR(180) NOT NULL,document_id INTEGER NULL,FOREIGN KEY(request_id) REFERENCES document_requests(id),FOREIGN KEY(document_id) REFERENCES documents(id))".$suffix);
}];

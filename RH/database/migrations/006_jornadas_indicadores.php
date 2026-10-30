<?php
return ['name'=>'Modelos de jornada, vigências e indicadores de premiação', 'up'=>function(PDO $db,string $driver):void {
    $id=$driver==='mysql'?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';$suffix=$driver==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    foreach([
        "CREATE TABLE IF NOT EXISTS work_templates(id $id,title VARCHAR(180) NOT NULL,kind VARCHAR(40) NOT NULL,slots TEXT NOT NULL,break_minutes INTEGER NOT NULL,lesson_minutes INTEGER NOT NULL,notes TEXT NOT NULL)",
        "CREATE TABLE IF NOT EXISTS work_assignments(id $id,employee_id INTEGER NOT NULL,template_id INTEGER NOT NULL,start VARCHAR(10) NOT NULL,end VARCHAR(10) NULL,snapshot TEXT NOT NULL,FOREIGN KEY(employee_id) REFERENCES employees(id),FOREIGN KEY(template_id) REFERENCES work_templates(id))",
        "CREATE TABLE IF NOT EXISTS performance_metrics(id $id,employee_id INTEGER NOT NULL,kind VARCHAR(40) NOT NULL,start VARCHAR(10) NOT NULL,end VARCHAR(10) NOT NULL,actual DECIMAL(12,2) NOT NULL,expected DECIMAL(12,2) NOT NULL,target DECIMAL(6,2) NULL,evidence TEXT NOT NULL,updated VARCHAR(30) NOT NULL,FOREIGN KEY(employee_id) REFERENCES employees(id))"
    ] as $sql)$db->exec($sql.$suffix);
}];

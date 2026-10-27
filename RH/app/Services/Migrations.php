<?php
declare(strict_types=1);
final class Migrations {
    private array $files;
    public function __construct(private Database $db,private string $storage){
        $db->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations(version VARCHAR(100) PRIMARY KEY,name VARCHAR(200) NOT NULL,checksum VARCHAR(64) NOT NULL,applied_at VARCHAR(30) NOT NULL)");
        $this->files=glob(dirname(__DIR__,2).'/database/migrations/*.php');sort($this->files);
    }
    public function status():array{
        $applied=array_column($this->db->all('SELECT * FROM schema_migrations'),null,'version');$rows=[];
        foreach($this->files as $file){$v=basename($file,'.php');$m=require $file;$a=$applied[$v]??null;$rows[]=['version'=>$v,'name'=>$m['name'],'applied'=>(bool)$a,'changed'=>$a&&!hash_equals($a['checksum'],hash_file('sha256',$file)),'applied_at'=>$a['applied_at']??null];}return $rows;
    }
    public function initial():void{if(!$this->db->one('SELECT version FROM schema_migrations WHERE version=?',['001_initial']))$this->apply('001_initial');}
    public function apply(string $version):void{
        $file=dirname(__DIR__,2).'/database/migrations/'.$version.'.php';if(!in_array($file,$this->files,true))throw new RuntimeException('Migration desconhecida.');
        if($this->db->one('SELECT version FROM schema_migrations WHERE version=?',[$version]))return;
        $m=require $file;
        $work=function()use($m,$version,$file){($m['up'])($this->db->pdo,$this->db->driver);$this->db->run('INSERT INTO schema_migrations(version,name,checksum,applied_at) VALUES(?,?,?,?)',[$version,$m['name'],hash_file('sha256',$file),gmdate('c')]);};
        if($this->db->driver==='sqlite')$this->db->transaction($work);else $work(); // MySQL faz commit implícito para DDL; migrations são retomáveis.
    }
    public function backup():string{
        $dir=$this->storage.'/backups';if(!is_dir($dir))mkdir($dir,0700,true);$name='pre-migration-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
        if($this->db->driver==='sqlite'){$file=$dir.'/'.$name.'.sqlite';$this->db->pdo->exec('VACUUM INTO '.$this->db->pdo->quote($file));}
        else{$file=$dir.'/'.$name.'.json';$tables=['schema_migrations','sectors','employees','users','schedules','punches','leaves','documents','audit','system_settings'];$dump=['created'=>gmdate('c'),'format'=>'radeane-rh-db-snapshot-v1','tables'=>[]];$this->db->transaction(function()use(&$dump,$tables){foreach($tables as $table)$dump['tables'][$table]=$this->db->all('SELECT * FROM '.$table);});file_put_contents($file,json_encode($dump,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),LOCK_EX);}
        chmod($file,0600);return basename($file);
    }
    public function pending():array{return array_values(array_filter($this->status(),fn($m)=>!$m['applied']));}
    public function run():array{
        foreach($this->status()as $m)if($m['changed'])throw new RuntimeException('Uma migration aplicada foi alterada. Revise o código antes de continuar.');
        $pending=$this->pending();if(!$pending)return ['applied'=>0,'backup'=>null];
        $backup=$this->backup();foreach($pending as $m)$this->apply($m['version']);return ['applied'=>count($pending),'backup'=>$backup];
    }
}

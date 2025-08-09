<?php

namespace mgusmano\seedgen\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class seedgen extends Command
{
    protected $signature = 'seedgen
                            {--o|output=seeders : Output directory (relative to database/)}
                            {--t|test : Test mode}';
    protected $description = 'Generate Laravel seeder file from existing PostgreSQL table data';
    protected $now;

    public function handle()
    {
        try {
            $this->now = now()->format('YmdHis');
            $this->info('🌱 seedgen started 🔥');
            //$this->info('🌱 output folder: ' . $this->option('output'));
            $this->generateCombinedSeeder();
            $this->info('✅ seedgen completed successfully!');
        } catch (\Exception $e) {
            $this->warn('❌ Error: ' . $e->getMessage());
            return 1;
        }
        return 0;
    }

    private function generateCombinedSeeder(): void
    {
        $tablesstring = '';


        if ($this->option('test')) {
            $tables = [
                (object) ['table_name' => 'accounts'],
                (object) ['table_name' => 'company_types'],
            ];
            $tablesstring = "accounts, company_types";
        } else {
            $tables = $this->getAllTables();
            foreach ($tables as $table) {
                $tablesstring .= $table->table_name . ', ';
            }
            $tablesstring = rtrim($tablesstring, ', ');
        }
        $tableNames = explode(',', $tablesstring);


        $this->info('🌱 Generating combined seeder file for ' . count($tables) . ' tables');
        $allData = [];
        foreach ($tableNames as $tableName) {
            $tableName = trim($tableName);
            if (!$this->tableExists($tableName)) {
                $this->warn("Table '{$tableName}' does not exist, skipping...");
                continue;
            }
            $data = $this->getTableData($tableName);
            if (!empty($data)) {
                $allData[$tableName] = $data;
                $this->line("📈 {$tableName}: " . count($data) . " records");
            }
        }
        if (empty($allData)) {
            $this->error("No data found in any of the specified tables");
            return;
        }
        $code = $this->generateCombinedSeederCode($allData);
        $filename = $this->writeCombinedSeederFile($code);
        $this->info("📝 Generated combined seeder: {$filename}");
    }

    private function getAllTables(): array
    {
        $query = "
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_type = 'BASE TABLE'
            ORDER BY table_name
        ";
        return DB::select($query);
    }

    private function tableExists(string $tableName): bool
    {
        $query = "
            SELECT COUNT(*) as count
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name = ?
        ";
        $result = DB::select($query, [$tableName]);
        return $result[0]->count > 0;
    }

    private function getTableData(string $tableName): array
    {
        $query = "SELECT * FROM \"{$tableName}\" ORDER BY 1";
        $data = DB::select($query);
        $cleanData = [];
        foreach ($data as $record) {
            $recordArray = (array) $record;
            unset($recordArray['created_at'], $recordArray['updated_at']);
            $primaryKey = Str::singular($tableName) . '_id';
            unset($recordArray[$primaryKey]);
            $cleanData[] = $recordArray;
        }
        return $cleanData;
    }

    private function generateCombinedSeederCode(array $allData): string
    {
        $tableNames = implode(', ', array_keys($allData));
        $code = "<?php

namespace Database\\Seeders;

//use Illuminate\\Database\\Console\\Seeds\\WithoutModelEvents;
use Illuminate\\Database\\Seeder;
use Illuminate\\Support\\Facades\\DB;

class CombinedDataSeeder$this->now extends Seeder
{
    /**
     * Run the database seeds.
     * Generated on: " . $this->now . "
     */
    public function run(): void
    {
    \t\$this->command->info('started!');  	
    
";

        foreach ($allData as $tableName => $data) {
            $code .= " \t\tDB::table('{$tableName}')->truncate();\n";
        }


        $code .= "    
        DB::transaction(function () {
            // Defer all deferrable constraints for the current transaction
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
";

        foreach ($allData as $tableName => $data) {
            $dataString = $this->formatDataForCode($data);
            $code .= "
\t\t\t// Seed {$tableName} (" . count($data) . " records)
\t\t\t\$this->command->info('{$tableName}' . ' (" . count($data) . " records)');  
\t\t\tDB::table('{$tableName}')->insert({$dataString});
";
        }
        $code .= "\n\t\t});\n\n\t\t\$this->command->info('ended!');\n\t}\n}";
        return $code;
    }

    private function formatDataForCode(array $data): string
    {
        $formatted = "[\n";
        foreach ($data as $record) {
            $formatted .= "\t\t\t\t[";
            foreach ($record as $key => $value) {
                $formattedValue = $this->formatValue($value);
                $formatted .= "'{$key}' => {$formattedValue},";
            }
            $formatted .= "],\n";
        }
        $formatted .= "\t\t\t]";
        return $formatted;
    }

    private function formatValue($value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        // if (is_numeric($value)) {
        //     return (string) $value;
        // }
        if (is_string($value)) {
            $escaped = str_replace("'", "\\'", $value);
            return "'{$escaped}'";
        }
        return "'" . (string) $value . "'";
    }

    private function writeCombinedSeederFile(string $code): string
    {
        $outputDir = $this->option('output');
        $filename = database_path("{$outputDir}/CombinedDataSeeder" . $this->now . ".php");
        $directory = dirname($filename);
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
        File::put($filename, $code);
        return $filename;
    }
}

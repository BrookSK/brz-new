<#
Corrige mojibake (UTF-8 interpretado como Windows-1252) nos arquivos alvo.
Grava UTF-8 sem BOM.

IMPORTANTE: este script NAO contem caracteres nao-ASCII no fonte, para evitar
problemas de encoding quando o Windows PowerShell 5.1 le o .ps1 como ANSI.
Todos os caracteres corretos sao construidos via code points Unicode.

Uso:
  -Report    : apenas relata (nao altera)
  -AllViews  : inclui Views/admin e Controllers alem dos dicionarios
#>
param(
    [switch]$Report,
    [switch]$AllViews
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

$utf8 = New-Object System.Text.UTF8Encoding($false)
$win1252 = [System.Text.Encoding]::GetEncoding(1252)

function Get-Mojibake([string]$correct) {
    $bytes = $utf8.GetBytes($correct)
    return $win1252.GetString($bytes)
}

# Caracteres corretos (por code point Unicode) que podem ter corrompido.
$cp = @(
    0x00E7, # c-cedilha minuscula
    0x00E3, # a-til
    0x00F5, # o-til
    0x00E9, # e-agudo
    0x00ED, # i-agudo
    0x00E1, # a-agudo
    0x00F3, # o-agudo
    0x00FA, # u-agudo
    0x00E2, # a-circunflexo
    0x00EA, # e-circunflexo
    0x00F4, # o-circunflexo
    0x00E0, # a-crase
    0x00E8, # e-grave
    0x00C7, # C-cedilha
    0x00C3, # A-til
    0x00D5, # O-til
    0x00C9, # E-agudo
    0x00CD, # I-agudo
    0x00C1, # A-agudo
    0x00D3, # O-agudo
    0x00DA, # U-agudo
    0x00C2, # A-circunflexo
    0x00CA, # E-circunflexo
    0x00D4, # O-circunflexo
    0x00AA, # ordinal feminino
    0x00BA, # ordinal masculino
    0x00B0, # grau
    0x2014, # travessao (em dash)
    0x2013, # en dash
    0x201C, # aspa dupla esquerda
    0x201D, # aspa dupla direita
    0x2018, # aspa simples esquerda
    0x2019, # aspa simples direita
    0x2026, # reticencias
    0x20AC, # euro
    0x2192, # seta direita
    0x2022, # bullet
    0x00B7  # ponto medio
)

# Sequencias multi-char (emojis) por code points
$multi = @(
    ,@(0x26A0, 0xFE0F)  # aviso (warning + VS16)
) + @(
    ,@(0x1F4AC)          # balao de fala (emoji, surrogate pair)
) + @(
    ,@(0x2705)           # check verde
) + @(
    ,@(0x274C)           # X vermelho
) + @(
    ,@(0x27A1, 0xFE0F)   # seta direita emoji
)

$correctList = @()
foreach ($code in $cp) { $correctList += [char]$code }
foreach ($seq in $multi) {
    $s = -join ($seq | ForEach-Object { [System.Char]::ConvertFromUtf32($_) })
    $correctList += $s
}

$pairs = @()
foreach ($c in $correctList) {
    $cs = [string]$c
    $moji = Get-Mojibake $cs
    if ($moji -ne $cs -and $moji.Length -gt 0) {
        $pairs += [pscustomobject]@{ Moji = $moji; Correct = $cs; Len = $moji.Length }
    }
}
$pairs = $pairs | Sort-Object Len -Descending

# Alvos
$targets = @()
$targets += (Join-Path $root 'app\lang\en.php')
$targets += (Join-Path $root 'app\lang\pt-BR.php')
if ($AllViews) {
    $targets += (Get-ChildItem -Recurse -Path (Join-Path $root 'app\Views\admin') -Include *.php | ForEach-Object { $_.FullName })
    $targets += (Get-ChildItem -Recurse -Path (Join-Path $root 'app\Controllers') -Include *.php | ForEach-Object { $_.FullName })
}
$targets = $targets | Select-Object -Unique | Where-Object { Test-Path $_ }

$reportLines = @()
$reportLines += "PAIRS (mojibake bytes -> correct codepoint):"
foreach ($p in $pairs) {
    $mb = (([string]$p.Moji).ToCharArray() | ForEach-Object { 'U+{0:X4}' -f [int]$_ }) -join ' '
    $cc = (([string]$p.Correct).ToCharArray() | ForEach-Object { 'U+{0:X4}' -f [int]$_ }) -join ' '
    $reportLines += ("  [{0}] -> [{1}]" -f $mb, $cc)
}
$reportLines += ""

$totalFiles = 0
$totalHits = 0
foreach ($file in $targets) {
    $content = [System.IO.File]::ReadAllText($file, $utf8)
    $count = 0
    foreach ($p in $pairs) {
        $count += ([regex]::Matches($content, [regex]::Escape($p.Moji))).Count
    }
    if ($count -gt 0) {
        $totalFiles++
        $totalHits += $count
        $reportLines += ("{0}  hits={1}" -f $file, $count)
        if (-not $Report) {
            $new = $content
            foreach ($p in $pairs) {
                $new = $new.Replace($p.Moji, $p.Correct)
            }
            [System.IO.File]::WriteAllText($file, $new, $utf8)
        }
    }
}
$reportLines += ""
$reportLines += "TOTAL FILES WITH MOJIBAKE: $totalFiles"
$reportLines += "TOTAL HITS: $totalHits"
$reportLines += ("MODE: " + $(if ($Report) { 'REPORT (no changes)' } else { 'APPLIED' }))

[System.IO.File]::WriteAllLines((Join-Path $root 'mojibake_report.txt'), $reportLines, $utf8)
Write-Output "done files=$totalFiles hits=$totalHits mode=$(if($Report){'report'}else{'applied'})"

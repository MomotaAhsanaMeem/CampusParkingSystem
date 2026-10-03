<?php
// includes/CampusParkPDF.php — Custom FPDF wrapper for CampusPark branded PDF reports
require_once __DIR__ . '/fpdf/fpdf.php';

class CampusParkPDF extends FPDF {
    protected string $reportTitle = 'Report';
    protected string $reportSubtitle = '';
    protected string $generatedBy = '';
    protected bool $isLandscape = false;

    public function __construct(string $orientation = 'P', string $title = 'CampusPark Report', string $subtitle = '', string $generatedBy = '') {
        parent::__construct($orientation, 'mm', 'A4');
        $this->reportTitle    = $title;
        $this->reportSubtitle = $subtitle;
        $this->generatedBy    = $generatedBy;
        $this->isLandscape    = strtoupper($orientation) === 'L';
        $this->SetAutoPageBreak(true, 18);
        $this->SetMargins(14, 14, 14);
    }

    public static function safe(string $str): string {
        $str = str_replace(
            ['—', '–', '’', '‘', '“', '”', '•', '…'],
            [' - ', '-', "'", "'", '"', '"', '*', '...'],
            $str
        );
        if (function_exists('iconv')) {
            $conv = @iconv('UTF-8', 'windows-1252//TRANSLIT', $str);
            if ($conv !== false) {
                return $conv;
            }
        }
        return utf8_decode($str);
    }

    // Page header
    public function Header(): void {
        $pageW = $this->isLandscape ? 297 : 210;
        $margin = 14;
        $contentW = $pageW - ($margin * 2);

        // Top Accent Bar
        $this->SetFillColor(8, 145, 178); // #0891B2
        $this->Rect(0, 0, $pageW, 4, 'F');

        $this->SetY(10);
        $logoPath = __DIR__ . '/../assets/images/logo.jpg';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, $margin, 10, 12, 12);
            $this->SetX($margin + 15);
        } else {
            $this->SetX($margin);
        }

        // Brand Title
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(15, 23, 42); // #0F172A
        $this->Cell(70, 6, self::safe('CampusPark'), 0, 0, 'L');

        // Right side: Generation metadata
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 116, 139);
        $metaText = 'Generated: ' . date('M j, Y - g:i A');
        if ($this->generatedBy !== '') {
            $metaText .= ' | ' . self::safe($this->generatedBy);
        }
        $this->Cell($contentW - 70 - (file_exists($logoPath) ? 15 : 0), 6, $metaText, 0, 1, 'R');

        // Subtitle line / Report title
        $this->SetX(file_exists($logoPath) ? ($margin + 15) : $margin);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(8, 145, 178);
        $this->Cell(70, 5, self::safe($this->reportTitle), 0, 0, 'L');

        if ($this->reportSubtitle !== '') {
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(100, 116, 139);
            $this->Cell($contentW - 70 - (file_exists($logoPath) ? 15 : 0), 5, self::safe($this->reportSubtitle), 0, 1, 'R');
        } else {
            $this->Ln(5);
        }

        // Divider rule
        $this->Ln(3);
        $this->SetDrawColor(226, 232, 240); // #E2E8F0
        $this->SetLineWidth(0.4);
        $this->Line($margin, $this->GetY(), $pageW - $margin, $this->GetY());
        $this->Ln(5);
    }

    // Page footer
    public function Footer(): void {
        $pageW = $this->isLandscape ? 297 : 210;
        $margin = 14;

        $this->SetY(-14);
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.3);
        $this->Line($margin, $this->GetY(), $pageW - $margin, $this->GetY());

        $this->SetY(-11);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(148, 163, 184); // #94A3B8
        $this->Cell(0, 5, self::safe('CampusPark Parking Management System - Official Document'), 0, 0, 'L');
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
    }

    // Helper: KPI Stat Box
    public function KpiCard(float $x, float $y, float $w, float $h, string $label, string $value, string $subtext = '', array $accentRgb = [8, 145, 178]): void {
        // Background card
        $this->SetFillColor(248, 250, 252); // #F8FAFC
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.3);
        $this->Rect($x, $y, $w, $h, 'DF');

        // Left accent border strip
        $this->SetFillColor($accentRgb[0], $accentRgb[1], $accentRgb[2]);
        $this->Rect($x, $y, 2.5, $h, 'F');

        // Label
        $this->SetXY($x + 5, $y + 3);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell($w - 7, 4, strtoupper(self::safe($label)), 0, 1, 'L');

        // Value
        $this->SetX($x + 5);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor($accentRgb[0], $accentRgb[1], $accentRgb[2]);
        $this->Cell($w - 7, 6, self::safe($value), 0, 1, 'L');

        // Subtext
        if ($subtext !== '') {
            $this->SetX($x + 5);
            $this->SetFont('Arial', '', 7);
            $this->SetTextColor(148, 163, 184);
            $this->Cell($w - 7, 4, self::safe($subtext), 0, 1, 'L');
        }
    }

    // Helper: Section Title
    public function SectionTitle(string $title, string $rightMeta = ''): void {
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(110, 6, self::safe($title), 0, 0, 'L');
        if ($rightMeta !== '') {
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(0, 6, self::safe($rightMeta), 0, 1, 'R');
        } else {
            $this->Ln(6);
        }
        $this->Ln(2);
    }
}

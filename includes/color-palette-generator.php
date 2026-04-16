<?php
/**
 * Color Palette Generator
 * Generates hierarchical color palettes starting from Faculty (parent color)
 * Programs inherit from Faculty, Courses inherit from Programs
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class NDS_ColorPaletteGenerator {
    
    private $faculty_primary_colors = [
        '#E53935', // Red
        '#1976D2', // Blue
        '#388E3C', // Green
        '#FFA000', // Amber
        '#8E24AA', // Purple
        '#0097A7', // Teal
        '#F57C00', // Orange
        '#5D4037', // Brown
        '#455A64', // Blue Grey
        '#C2185B', // Pink
        '#7B1FA2', // Deep Purple
        '#0288D1', // Light Blue
        '#689F38', // Light Green
        '#FF5722', // Deep Orange
        '#607D8B', // Grey
        '#D32F2F', // Red 700
        '#303F9F', // Indigo
        '#00796B', // Teal 700
        '#D84315', // Deep Orange 800
        '#5D4037'  // Brown 700
    ];
    
    private $color_variations = [
        'dark' => ['lightness' => -30, 'saturation' => 10],
        'medium-dark' => ['lightness' => -15, 'saturation' => 5],
        'medium' => ['lightness' => 0, 'saturation' => 0],
        'medium-light' => ['lightness' => 15, 'saturation' => -5],
        'light' => ['lightness' => 30, 'saturation' => -10],
        'vibrant' => ['lightness' => 0, 'saturation' => 20],
        'muted' => ['lightness' => 0, 'saturation' => -20],
        'pastel' => ['lightness' => 40, 'saturation' => -30],
        'deep' => ['lightness' => -20, 'saturation' => 15],
        'bright' => ['lightness' => 10, 'saturation' => 25]
    ];
    
    /**
     * Convert hex to HSL
     */
    private function hex_to_hsl($hex) {
        $hex = str_replace('#', '', $hex);
        
        // Handle 3-digit hex
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        
        $l = ($max + $min) / 2;
        $s = 0;
        $h = 0;
        
        if ($max !== $min) {
            $delta = $max - $min;
            $s = $l > 0.5 ? $delta / (2 - $max - $min) : $delta / ($max + $min);
            
            switch ($max) {
                case $r:
                    $h = (($g - $b) / $delta) + ($g < $b ? 6 : 0);
                    break;
                case $g:
                    $h = (($b - $r) / $delta) + 2;
                    break;
                case $b:
                    $h = (($r - $g) / $delta) + 4;
                    break;
            }
            $h /= 6;
        }
        
        return [
            'h' => round($h * 360),
            's' => round($s * 100),
            'l' => round($l * 100)
        ];
    }
    
    /**
     * Convert HSL to hex
     */
    private function hsl_to_hex($h, $s, $l) {
        $h = $h % 360;
        $s = max(0, min(100, $s)) / 100;
        $l = max(0, min(100, $l)) / 100;
        
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs((($h / 60) % 2) - 1));
        $m = $l - ($c / 2);
        
        if ($h >= 0 && $h < 60) {
            [$r, $g, $b] = [$c, $x, 0];
        } elseif ($h >= 60 && $h < 120) {
            [$r, $g, $b] = [$x, $c, 0];
        } elseif ($h >= 120 && $h < 180) {
            [$r, $g, $b] = [0, $c, $x];
        } elseif ($h >= 180 && $h < 240) {
            [$r, $g, $b] = [0, $x, $c];
        } elseif ($h >= 240 && $h < 300) {
            [$r, $g, $b] = [$x, 0, $c];
        } else {
            [$r, $g, $b] = [$c, 0, $x];
        }
        
        $r = str_pad(dechex(round(($r + $m) * 255)), 2, '0', STR_PAD_LEFT);
        $g = str_pad(dechex(round(($g + $m) * 255)), 2, '0', STR_PAD_LEFT);
        $b = str_pad(dechex(round(($b + $m) * 255)), 2, '0', STR_PAD_LEFT);
        
        return '#' . $r . $g . $b;
    }
    
    /**
     * Adjust color based on variations
     */
    private function adjust_color($hex, $adjustments) {
        $hsl = $this->hex_to_hsl($hex);
        
        if (isset($adjustments['lightness'])) {
            $hsl['l'] = max(0, min(100, $hsl['l'] + $adjustments['lightness']));
        }
        
        if (isset($adjustments['saturation'])) {
            $hsl['s'] = max(0, min(100, $hsl['s'] + $adjustments['saturation']));
        }
        
        if (isset($adjustments['hue'])) {
            $hsl['h'] = ($hsl['h'] + $adjustments['hue']) % 360;
        }
        
        return $this->hsl_to_hex($hsl['h'], $hsl['s'], $hsl['l']);
    }
    
    /**
     * Generate program color from faculty color
     */
    public function generate_program_color($faculty_color, $program_index, $total_programs = 1) {
        $variation_types = array_keys($this->color_variations);
        $variation_index = $program_index % count($variation_types);
        $variation_type = $variation_types[$variation_index];
        $adjustments = $this->color_variations[$variation_type];
        
        // Add slight hue variation based on program index
        $adjustments['hue'] = (($program_index * 15) % 60) - 30; // -30 to +30 degrees
        
        $program_color = $this->adjust_color($faculty_color, $adjustments);
        
        return [
            'hex' => $program_color,
            'variation' => $variation_type,
            'name' => ucfirst($variation_type) . ' Program Color'
        ];
    }
    
    /**
     * Generate course palette from program color
     */
    public function generate_course_palette($program_color, $total_courses = 1) {
        $palette = [];
        $hsl = $this->hex_to_hsl($program_color);
        
        // Generate gradient from dark to light
        for ($i = 0; $i < $total_courses; $i++) {
            $position = $total_courses > 1 ? ($i / ($total_courses - 1)) : 0.5;
            
            if ($position < 0.5) {
                $lightness = max(10, $hsl['l'] * (1 - $position * 1.8));
                $saturation = min(100, $hsl['s'] * (1 + $position * 0.5));
            } else {
                $lightness = min(90, $hsl['l'] * (1 + ($position - 0.5) * 1.8));
                $saturation = max(20, $hsl['s'] * (1 - ($position - 0.5) * 0.5));
            }
            
            $course_color = $this->hsl_to_hex($hsl['h'], $saturation, $lightness);
            
            $palette[] = [
                'hex' => $course_color,
                'name' => 'Course ' . ($i + 1),
                'position' => $i + 1,
                'lightness' => round($lightness)
            ];
        }
        
        return $palette;
    }
    
    /**
     * Generate complete palette for a program
     * This is the "bible" stored in JSON format
     */
    public function generate_program_palette($faculty_color, $program_index, $total_programs, $total_courses = 20) {
        // Generate program color from faculty
        $program_color_data = $this->generate_program_color($faculty_color, $program_index, $total_programs);
        
        // Generate course palette from program color
        $course_palette = $this->generate_course_palette($program_color_data['hex'], $total_courses);
        
        $palette = [
            'faculty_color' => $faculty_color,
            'program_color' => [
                'hex' => $program_color_data['hex'],
                'variation' => $program_color_data['variation'],
                'name' => $program_color_data['name']
            ],
            'course_palette' => $course_palette,
            'generated_at' => current_time('mysql'),
            'program_index' => $program_index
        ];
        
        return $palette;
    }
    
    /**
     * Get default faculty color by index
     */
    public function get_default_faculty_color($faculty_index = 0) {
        $index = $faculty_index % count($this->faculty_primary_colors);
        return $this->faculty_primary_colors[$index];
    }
    
    /**
     * Get course color from palette
     */
    public function get_course_color_from_palette($palette_json, $course_index = 0) {
        if (empty($palette_json)) {
            return '#607D8B'; // Default grey
        }
        
        $palette = is_string($palette_json) ? json_decode($palette_json, true) : $palette_json;
        
        if (!isset($palette['course_palette']) || empty($palette['course_palette'])) {
            return $palette['program_color']['hex'] ?? '#607D8B';
        }
        
        $course_palette = $palette['course_palette'];
        $index = $course_index % count($course_palette);
        
        return $course_palette[$index]['hex'] ?? '#607D8B';
    }
}

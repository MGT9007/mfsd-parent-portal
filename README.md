# MFSD Parent Portal Plugin

A WordPress plugin that provides parents with a dashboard to view their linked student's progress in the High Performance Pathway foundation course.

## Installation

1. Upload the `mfsd-parent-portal` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Create a new page and add the shortcode `[mfsd_parent_portal]`
4. Assign this page to parent users (via menu or direct link)

## Requirements

- WordPress 5.0+
- PHP 7.4+
- The following MFSD plugins must be active:
  - Word Association plugin (with `wp_mfsd_word_associations` table)
  - Junk Jobs plugin (with `wp_mfsd_ai_junk_jobs_results` table)
  - Personality Test plugin (with `wp_mfsd_ptest_answers` and `wp_mfsd_ptest_results` tables)
  - Weekly RAG plugin (with `wp_mfsd_rag_answers` table)
- Parent-Student linking table (`wp_mfsd_parent_student_links`)

## Features

### Current Features (Week 1)

- **Student Overview**: Shows linked student name, year group, school, and avatar
- **Progress Tracking**: Overall completion percentage displayed as progress ring
- **Word Association**: Shows completion status and AI insights
- **Junk Jobs**: Shows progress through workflow stages and career analysis
- **Personality Test (MBTI)**: Displays MBTI type, DISC scores with visual bars, and Steve Says summary
- **Weekly RAG**: Shows total score and R/A/G breakdown

### Extensibility

The plugin is designed to easily add:
- **Super Strengths** activity (Week 1) - placeholder ready
- **Week 2 & 3** activities - structure in place
- Additional activity types

## Architecture

```
mfsd-parent-portal/
├── mfsd-parent-portal.php          # Main plugin file
├── includes/
│   ├── class-parent-portal-data.php    # Database queries & data layer
│   └── class-parent-portal-renderer.php # HTML output
├── assets/
│   ├── css/
│   │   └── parent-portal.css       # Styling
│   └── js/
│       └── parent-portal.js        # Interactivity
└── README.md
```

## Adding New Activities

### 1. Register the activity in the data class

In `class-parent-portal-data.php`, add to the `$activities` array:

```php
private $activities = [
    1 => [
        // ... existing activities ...
        'super_strengths' => [
            'name' => 'Super Strengths',
            'icon' => '💪',
            'description' => 'Discovering personal strengths',
            'coming_soon' => false  // Set to false when ready
        ]
    ]
];
```

### 2. Create the status method

Add a method following the naming convention `get_{activity_key}_status`:

```php
private function get_super_strengths_status($student_id, $week_num = 1) {
    // Query your activity's table
    // Return array with: status, progress, progress_text, and any result data
}
```

### 3. Add the results renderer

In `class-parent-portal-renderer.php`, add a case in `render_activity_results()`:

```php
case 'super_strengths':
    $this->render_super_strengths_results($activity);
    break;
```

Then create the render method:

```php
private function render_super_strengths_results($activity) {
    // Output HTML for the results
}
```

## Adding New Weeks

In `class-parent-portal-data.php`, extend the `$activities` array:

```php
private $activities = [
    1 => [ /* Week 1 activities */ ],
    2 => [
        'dream_jobs' => [
            'name' => 'Dream Jobs',
            'icon' => '🌟',
            'description' => 'Exploring ideal careers'
        ],
        // ... more activities
    ],
    3 => [ /* Week 3 activities */ ]
];
```

Update `$week_names` in `render_week_section()` if needed.

## User Meta Fields

The plugin expects these user meta fields for students:
- `year_group` - Student's school year
- `school` - School name

## Shortcode

```
[mfsd_parent_portal]
```

No parameters required. The shortcode automatically:
1. Checks user is logged in
2. Queries linked students for the current user
3. Displays progress for all linked students

## Styling Customization

CSS variables can be overridden in your theme:

```css
.mfsd-pp {
    --pp-primary: #your-color;
    --pp-success: #your-color;
    --pp-warning: #your-color;
    /* See parent-portal.css for all variables */
}
```

## Support

For issues or feature requests, contact the MFSD development team.

# AWS Practice Exam Manager

A simple web application to practice AWS Certified Cloud Practitioner exam questions.

## Requirements

- Docker
- Docker Compose
- WSL2 (Windows Subsystem for Linux 2)

## Setup and Running

1. Clone the repository:
```bash
git clone <repository-url>
cd study-manager
```

2. Build and start the containers:
```bash
docker-compose up -d --build
```

3. Access the application:
Open your browser and navigate to: http://localhost:8000

## Project Structure

```
study-manager/
├── api/                     # API endpoints
│   ├── data/               # Data storage
│   │   ├── cache/         # Parsed question cache
│   │   ├── questions/     # Question files (markdown)
│   │   ├── results/       # User progress & results
│   │   └── stats/         # Statistics data
│   ├── load-progress.php   # Load user's saved answers and progress
│   ├── load-stats.php      # Load statistics and completion rates
│   ├── questions.php       # Retrieve and parse exam questions
│   ├── save-progress.php   # Save user's answers and progress
│   └── wrong-answers.php   # Track and manage incorrect answers
├── assets/                # Static assets
│   ├── main.js           # Main JavaScript logic
│   └── style.css         # Main stylesheet
├── includes/             # PHP classes
│   ├── Parser.php       # Markdown parser
│   └── ExamManager.php  # Exam management
├── Dockerfile           # Docker configuration
├── docker-compose.yml   # Docker Compose configuration
├── apache.conf          # Apache configuration (required for Docker)
                        # - Sets document root and permissions
                        # - Configures virtual host and logging
                        # - Essential for web server setup
├── composer.json        # PHP dependencies and autoloading
                        # - Requires PHP 7.4+ and Parsedown
                        # - PSR-4 autoloading for includes/
├── .gitignore          # Version control exclusions
                        # - Excludes cache and results
                        # - Ignores IDE and OS files
                        # - Preserves empty directories
└── index.php           # Main application entry point
```

## Questions from
https://github.com/kananinirav/AWS-Certified-Cloud-Practitioner-Notes

## Features

- Parse and display AWS practice exam questions from markdown files
- Track user progress and answers
- Save progress automatically
- Simple and responsive interface
- No database required - file-based storage

## API Endpoints

The application provides several API endpoints to manage exam functionality:

### Data Management
- **load-progress.php**
  - Loads user's saved answers and progress
  - Retrieves completion status for each exam
  - Returns correct/incorrect answer history

- **save-progress.php**
  - Saves user's answers to questions
  - Updates progress and completion status
  - Records answer correctness and timestamps

### Question Handling
- **questions.php**
  - Retrieves and parses exam questions from markdown files
  - Returns formatted question data with options
  - Handles multiple choice and single answer questions

### Statistics and Analysis
- **load-stats.php**
  - Calculates and returns exam statistics
  - Provides completion rates and success metrics
  - Tracks overall performance data

- **wrong-answers.php**
  - Tracks and manages incorrect answers
  - Provides review functionality for missed questions
  - Helps identify areas needing improvement

## Development

The application uses:
- PHP 8.2
- Apache web server
- Bootstrap 5 for UI
- jQuery for dynamic interactions

## Front-end Architecture

The front-end code is organized following these principles:
- Separated CSS and JavaScript in dedicated asset files
- Component-based JavaScript architecture with templates
- Clean and maintainable code structure
- Modular and reusable UI components

### JavaScript Structure
- `assets/main.js`: Core application logic with:
  - HTML template management
  - Component-based UI rendering
  - Event handling
  - AJAX interactions
  - Modular template functions

### CSS Structure
- `assets/style.css`: Organized styles with:
  - Component-specific styles
  - Consistent naming conventions
  - Responsive design rules

All application files are mounted as a volume in the Docker container, so changes to the code will be reflected immediately without rebuilding the container.
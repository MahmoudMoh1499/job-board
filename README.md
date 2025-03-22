# Laravel Job Board with Advanced Filtering

A robust Laravel application that manages job listings with complex filtering capabilities similar to Airtable. The system employs Entity-Attribute-Value (EAV) design patterns alongside traditional relational database models to handle different job types with varying attributes.

## Table of Contents

- [Laravel Job Board with Advanced Filtering](#laravel-job-board-with-advanced-filtering)
  - [Table of Contents](#table-of-contents)
  - [Features](#features)
  - [Requirements](#requirements)
  - [Installation](#installation)
  - [Database Structure](#database-structure)
    - [Core Tables](#core-tables)
    - [Pivot Tables](#pivot-tables)
    - [EAV System Tables](#eav-system-tables)
  - [API Documentation](#api-documentation)
    - [Endpoints](#endpoints)
      - [List Jobs](#list-jobs)
      - [Get Job Details](#get-job-details)
    - [Filtering Syntax](#filtering-syntax)
      - [Basic Operators](#basic-operators)
      - [Relationship Operators](#relationship-operators)
      - [Logical Operators](#logical-operators)
      - [Grouping](#grouping)
      - [Field Types](#field-types)
    - [Filter Examples](#filter-examples)
  - [Design Decisions](#design-decisions)
    - [EAV Implementation](#eav-implementation)
    - [Filter Builder Service](#filter-builder-service)
    - [Query Optimization](#query-optimization)
  - [Performance Considerations](#performance-considerations)
    - [Indexing Strategy](#indexing-strategy)
    - [Query Execution](#query-execution)
    - [Future Scalability](#future-scalability)
  - [API Testing](#api-testing)
    - [Postman Collection](#postman-collection)

## Features

- Core job listing management with standard fields
- Many-to-many relationships for languages, locations, and categories
- Entity-Attribute-Value (EAV) implementation for dynamic job attributes
- Advanced filtering API with complex query capabilities
- Support for logical operators and condition grouping

## Requirements

- PHP 8.1+
- Laravel 10.x
- MySQL 8.0+ or PostgreSQL 13+
- Composer

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/laravel-job-board.git
   cd laravel-job-board
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Copy the example environment file:
   ```bash
   cp .env.example .env
   ```

4. Generate application key:
   ```bash
   php artisan key:generate
   ```

5. Configure your database connection in the `.env` file:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=job_board
   DB_USERNAME=root
   DB_PASSWORD=
   ```

6. Run migrations and seeders:
   ```bash
   php artisan migrate --seed
   ```

7. Start the development server:
   ```bash
   php artisan serve
   ```

## Database Structure

The application uses a hybrid approach combining traditional relational models with an EAV system:

### Core Tables
- `jobs` - Contains standard job listing information
- `languages` - Programming languages
- `locations` - Job locations
- `categories` - Job categories/departments

### Pivot Tables
- `job_language` - Associates jobs with required programming languages
- `job_location` - Associates jobs with possible locations
- `job_category` - Associates jobs with categories

### EAV System Tables
- `attributes` - Defines dynamic attributes (name, type, options)
- `job_attribute_values` - Stores the actual values for each job's attributes

## API Documentation

### Endpoints

#### List Jobs
```
GET /api/jobs
```
Returns a paginated list of jobs with optional filtering.

**Query Parameters:**
- `filter`: Complex filter expression (see Filtering Syntax)
- `page`: Page number for pagination (default: 1)
- `per_page`: Items per page (default: 15)
- `sort`: Field to sort by (default: created_at)
- `order`: Sort order ('asc' or 'desc', default: 'desc')

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Senior Laravel Developer",
      "description": "...",
      "company_name": "Acme Inc",
      "salary_min": 80000,
      "salary_max": 120000,
      "is_remote": true,
      "job_type": "full-time",
      "status": "published",
      "published_at": "2025-03-01T00:00:00.000000Z",
      "created_at": "2025-03-01T00:00:00.000000Z",
      "updated_at": "2025-03-01T00:00:00.000000Z",
      "languages": [
        {"id": 1, "name": "PHP"},
        {"id": 2, "name": "JavaScript"}
      ],
      "locations": [
        {"id": 1, "city": "New York", "state": "NY", "country": "USA"}
      ],
      "categories": [
        {"id": 3, "name": "Web Development"}
      ],
      "attributes": [
        {"name": "years_experience", "type": "number", "value": 5},
        {"name": "team_size", "type": "number", "value": 8},
        {"name": "offers_health_insurance", "type": "boolean", "value": true}
      ]
    },
    // More job listings...
  ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "path": "http://localhost:8000/api/jobs",
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```

#### Get Job Details
```
GET /api/jobsw
```
Returns detailed information about a all jobs

### Filtering Syntax

The API supports a powerful filtering syntax through the `filter` query parameter. The filtering system allows for complex queries with the following capabilities:

#### Basic Operators
- Equality: `=`, `!=`
- Comparison: `>`, `<`, `>=`, `<=`
- Contains: `LIKE`
- Multiple values: `IN`

#### Relationship Operators
- Has any of: `HAS_ANY`
- Is any of: `IS_ANY`
- Existence: `EXISTS`

#### Logical Operators
- AND: `AND`
- OR: `OR`

#### Grouping
- Parentheses: `(...)` for grouping conditions

#### Field Types
1. **Standard Fields**:
   - Access directly by field name: `title`, `salary_min`, etc.

2. **Relationship Fields**:
   - Access by relationship name: `languages`, `locations`, `categories`

3. **EAV Attributes**:
   - Access using `attribute:` prefix followed by attribute name: `attribute:years_experience`

### Filter Examples

1. **Basic Filtering**
   ```
   /api/jobs?filter=title LIKE "Senior" AND salary_min>=90000
   ```
   Find senior positions with minimum salary of $90,000.

2. **Relationship Filtering**
   ```
   /api/jobs?filter=languages HAS_ANY (PHP,JavaScript)
   ```
   Find jobs requiring either PHP or JavaScript.

3. **EAV Attribute Filtering**
   ```
   /api/jobs?filter=attribute:years_experience>=3
   ```
   Find jobs requiring at least 3 years of experience.

4. **Complex Filtering with Grouping**
   ```
   /api/jobs?filter=(job_type=full-time AND (languages HAS_ANY (PHP,JavaScript))) AND (locations IS_ANY (New York,Remote)) AND attribute:years_experience>=3
   ```
   Find full-time jobs requiring PHP or JavaScript, located in New York or Remote, and requiring at least 3 years of experience.

5. **Logical Operators and Multiple Conditions**
   ```
   /api/jobs?filter=(salary_min>=80000 AND salary_max<=120000) OR (is_remote=true AND job_type=contract)
   ```
   Find jobs with salary range $80K-$120K OR remote contract jobs.

## Design Decisions

### EAV Implementation
The EAV system was chosen to provide flexibility for different job types with varying attributes while maintaining query performance. The design:
- Uses a type field to handle different data types (text, number, boolean, date, select)
- Stores options for select-type attributes as JSON
- Maintains relationships to the core job model

### Filter Builder Service
The `JobFilterService` was designed to:
- Parse complex filter expressions
- Translate them into efficient Eloquent queries
- Support nested conditions and logical operations
- Handle different field types appropriately

### Query Optimization
Several strategies were implemented to ensure query efficiency:
- Eager loading of relationships to prevent N+1 problems
- Strategic indexing on frequently filtered fields
- Chunk processing for large datasets

## Performance Considerations

### Indexing Strategy
- B-Tree indexes on standard filtering fields (title, salary, job_type, etc.)
- Composite indexes on frequently combined fields
- Full-text indexes for text search on descriptions

### Query Execution
- Limit returned fields when possible
- Paginate results to prevent memory issues
- Use database transactions for data consistency
- Implement query timeouts for complex filters

### Future Scalability
- Consider partitioning for very large datasets
- Implement caching layer for common queries
- Add rate limiting for API endpoints
- Consider read replicas for high-traffic deployments


## API Testing
### Postman Collection

The repository includes a comprehensive Postman collection that demonstrates all the filtering capabilities of the API. The collection is organized into different categories of filtering examples:

- **Basic Field Filtering**: Simple equality, numeric comparison, boolean, text search, and date filtering
- **Relationship Filtering**: Examples for filtering based on related data like languages, locations, and categories
- **EAV Attribute Filtering**: Filtering by dynamic attributes in the EAV system
- **Complex Queries**: Advanced examples combining multiple conditions with logical operators

To use the collection:

1. Import `Advanced API Filtering Examples.json` into Postman
2. Update the `base_url` variable in your Postman environment to match your local or production API URL
3. Run the requests to see examples of the filtering syntax in action

This collection serves as both documentation and a testing tool for the API's filtering capabilities.

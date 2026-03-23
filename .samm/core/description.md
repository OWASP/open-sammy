# SAMM Assessment Format

The core SAMM Assessment Format provides a standardized JSON structure for exchanging security assessment data between tools and platforms.

## Features

- **Assessment Metadata**: Includes organization name, scope, date, and version information
- **Answer Storage**: Stores assessment answers with question codes and scores
- **Extension Support**: Allows additional data through a flexible extension mechanism

## Structure

```json
{
  "formatVersion": "1.0.0",
  "assessment": {
    "version": "1.0.0",
    "organization": "Organization Name",
    "scope": "Assessment Scope",
    "date": "2024-01-15",
    "answers": [
      {
        "questionCode": "G-SM-A-1",
        "answerScore": 0.25
      }
    ],
    "extensions": []
  }
}
```

## Compatibility

This format is compatible with OWASP SAMM and other security frameworks that follow the hierarchical element/question structure.

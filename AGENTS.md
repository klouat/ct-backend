# GLOBAL RULES

## Rule Priority

These are global defaults.

Project-specific rules may override these rules when necessary.
Language-specific conventions take precedence over generic rules.

---

## 1. Naming Conventions

Use widely accepted naming conventions for the language or framework.

Examples:

Python → snake_case  
JavaScript → camelCase  
Java / C# → PascalCase for classes  
Go → MixedCaps  
SQL → snake_case  

Additional requirements:

- Use English only for identifiers
- Names must be descriptive and consistent
- Avoid abbreviations unless widely understood
- Avoid single-letter variable names except for simple loops

---

## 2. Code Formatting & Readability

Code must be clean, readable, and consistent.

Guidelines:

- Prefer clarity over cleverness
- Keep functions small and focused
- Avoid deeply nested logic
- Break complex logic into smaller functions
- Use whitespace to improve readability

Alignment rules:

- Align operators only when it improves readability
- Do not force alignment in complex expressions

---

## 3. Code Organization

Separate concerns clearly:

- Business logic
- Data access
- API / controllers
- Utilities
- Configuration

Rules:

- Each function must have one clear responsibility
- Avoid circular dependencies
- Avoid unnecessary abstractions
- Keep modules cohesive
- Prefer composition over inheritance

---

## 4. Comments Policy

Do not overuse comments.

Comments must be:

- Short
- Clear
- Used only where logic is non-obvious

Never:

- Comment obvious code
- Explain what the code does when the code is self-explanatory

Prefer:

- Self-documenting code
- Clear function names

---

# BACKEND RULES

## 5. Error Handling

All errors must be handled and logged.

Never:

- Swallow exceptions silently
- Ignore failed operations

Requirements:

Logs must include:

- Error message
- Context
- Relevant identifiers
- Request or operation details

Errors must:

- Fail fast when appropriate
- Return safe error responses
- Never expose internal details to clients

---

## 6. Logging

Use structured logging.

Use standard log levels:

DEBUG  
INFO  
WARNING  
ERROR  
CRITICAL  

Rules:

- Log important operations
- Log failures
- Log security events
- Avoid excessive logging
- Never log secrets

---

## 7. Performance & Data Optimization

Performance is required by default.

Rules:

- Send only required fields
- Use pagination for large datasets
- Avoid unnecessary loops
- Avoid unnecessary API calls
- Avoid unnecessary database queries
- Avoid blocking operations when async is available

Prefer:

- Indexed database queries
- Caching where appropriate
- Batch operations
- Lazy loading when appropriate
- Streaming for large data

Avoid:

- N+1 queries
- Full table scans when avoidable
- Large payload responses

---

## 8. Database Rules

Rules:

- Use indexes on frequently queried fields
- Use transactions for multi-step writes
- Use constraints and foreign keys
- Validate data before writing
- Use parameterized queries
- Avoid SELECT *

Never:

- Trust client-provided IDs
- Disable constraints without reason

---

# API RULES

## 9. API Design

Rules:

- Validate all request input
- Validate authorization on every protected action
- Use consistent response structure
- Use proper HTTP status codes
- Use pagination for list endpoints
- Use rate limiting for sensitive endpoints

Never:

- Expose internal errors
- Expose stack traces
- Trust client validation

Prefer:

- REST APIs unless specified otherwise
- Idempotent operations where possible

---

## 10. Reliability

External operations must be resilient.

Rules:

- All external requests must have timeouts
- Retry transient failures when safe
- Use exponential backoff for retries
- Handle partial failures gracefully
- Fail safely

---

## 11. Configuration

Rules:

- Use environment variables for configuration
- Never hardcode credentials
- Never hardcode secrets
- Do not store secrets in source code
- Provide sensible defaults when appropriate

---

# SECURITY RULES

## 12. Security Principles

Security is mandatory.

Always:

- Apply least privilege
- Validate authorization server-side
- Validate authentication server-side
- Use secure defaults
- Use HTTPS only
- Treat all input as untrusted
- Protect sensitive data
- Log suspicious behavior

Never:

- Trust client-side checks
- Expose secrets
- Expose internal system details
- Assume input is safe

---

## 13. Security Measures

Protect against:

- OWASP Top 10 risks
- API security risks
- Dependency vulnerabilities
- Infrastructure misconfiguration
- Authentication and authorization flaws
- Input handling vulnerabilities
- File handling vulnerabilities
- Data exposure risks
- Abuse and fraud patterns
- Business logic attacks

Examples of threats include but are not limited to:

- Injection attacks (SQL, NoSQL, Command)
- Cross-Site Scripting (XSS)
- Cross-Site Request Forgery (CSRF)
- Server-Side Request Forgery (SSRF)
- XML External Entity (XXE)
- Path Traversal
- Deserialization attacks
- Broken Access Control
- Authentication bypass
- Session hijacking
- Replay attacks
- Brute force attacks
- Credential stuffing
- Rate limit abuse
- File upload abuse
- Clickjacking
- Open redirects
- Race conditions
- Sensitive data exposure
- Security misconfiguration
- Dependency vulnerabilities
- Denial-of-Service (DoS)
- Business logic abuse

---

## 14. Security Requirements

All systems must:

- Validate and sanitize input
- Use parameterized queries
- Enforce authorization checks
- Enforce authentication checks
- Use rate limiting where appropriate
- Use secure headers
- Protect sessions securely
- Encrypt sensitive data in transit
- Encrypt sensitive data at rest when required
- Rotate secrets when necessary
- Keep dependencies updated

---

# DEFAULT ENGINEERING PRACTICES

## Defaults

- Use async/await instead of callbacks when supported
- Prefer REST APIs unless specified otherwise
- Use logging instead of print statements
- Use environment variables for configuration
- Write predictable, maintainable code

---

## Code Quality

Prefer:

- Simple solutions
- Clear logic
- Predictable behavior

Avoid:

- Over-engineering
- Premature optimization
- Hidden side effects
- Global mutable state
# YouTube Video Generator SaaS - Copilot Instructions

<!-- Use this file to provide workspace-specific custom instructions to Copilot. For more details, visit https://code.visualstudio.com/docs/copilot/copilot-customization#_use-a-githubcopilotinstructionsmd-file -->

## Project Overview
This is a YouTube video generator SaaS application built with:
- **Backend**: Laravel (latest version)
- **Frontend**: Vue.js 3 + Inertia.js  
- **Database**: MySQL
- **AI Workflow**: n8n automation (Docker)
- **APIs**: Google OAuth 2.0, YouTube API, Pexels API, TTS services
- **Containerization**: Docker Compose

## Core Features
1. User authentication via email and Google OAuth (YouTube login)
2. Dashboard for managing generated videos
3. Manual video creation with AI-generated scripts, visuals, and voiceover
4. Automatic "Autopilot" channel mode with scheduled video generation
5. YouTube integration for direct uploads
6. Admin panel for user and quota management

## Architecture Guidelines
- Follow Laravel best practices and conventions
- Use Inertia.js for seamless SPA experience
- Implement proper error handling and validation
- Use queues for long-running tasks (video generation)
- Secure OAuth token storage per user
- Multi-tenant architecture support

## Code Standards
- Use TypeScript for Vue components where possible
- Follow PSR standards for PHP code
- Implement proper API versioning
- Use Laravel's built-in features (validation, middleware, etc.)
- Write comprehensive tests for critical functionality

## Security Considerations
- Secure handling of OAuth tokens and refresh tokens
- Rate limiting for API endpoints
- Input validation and sanitization
- CSRF protection
- Proper authentication middleware

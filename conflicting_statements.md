# Conflicting Statements in Cyblex Documentation

## 1. Frontend Technology Stack Conflicts

### Conflict 1: React vs Bootstrap
**Documentation Claims:**
- Section 2.7.2: "Frontend: Bootstrap"
- Section 2.2 (Stakeholder table): "Use of modular design (React + Node)"
- Section 3.6: "libraries such as Formik and Yup" (React-specific libraries)

**Actual Project Structure:**
- Uses Bootstrap (confirmed by login.html, register.html, index.html)
- Uses vanilla JavaScript (js/login.js, js/client-dashboard.js, etc.)
- No React components or files found

**Resolution Needed:** Remove all references to React, Formik, and Yup. Update Section 2.2 stakeholder table to reflect Bootstrap + vanilla JavaScript stack.

---

## 2. Backend Technology Stack Conflicts

### Conflict 2: Node.js vs PHP/Python
**Documentation Claims:**
- Section 2.2: "Use of modular design (React + Node)"
- Section 2.7.1: "Backend: Python, PHP"

**Actual Project Structure:**
- Backend: PHP (api/ directory with multiple .php files)
- Real-time: Python (websocket_server.py)
- No Node.js files found

**Resolution Needed:** Remove all references to Node.js. Clarify that backend uses PHP for API endpoints and Python for WebSocket server.

---

## 3. Missing Implementation References

### Conflict 3: Missing API Endpoints
**Documentation Claims (Section 4.3.2):**
- `auth.php, check_auth.php: User authentication endpoints`
- `submit_query.php, get_query_details.php: Query management endpoints`
- `send_message.php, get_messages.php: Messaging system endpoints`
- `register.php, check_user.php: User management endpoints`
- `verify-advisor.php, get-pending-verifications.php: Admin-related endpoints`

**Actual Project Structure:**
- All mentioned files exist in api/ directory
- Additional files not mentioned: `start_chat.php`, `complete_query.php`, `accept_query.php`, `submit_review.php`, `submit-rating.php`, etc.

**Resolution Needed:** Update documentation to include all actual API endpoints or remove references to non-existent files.

---

## 4. Authentication Implementation Conflicts

### Conflict 4: Login Implementation
**Documentation Claims (Section 4.2.1):**
- "The login module was built using a combination of HTML/CSS for the frontend and PHP for backend authentication"
- References "Figure 4.2.1.2 shows the code snippet from login.html"

**Actual Implementation:**
- Frontend: HTML with Bootstrap + vanilla JavaScript (js/login.js)
- Backend: PHP API endpoints (api/auth.php, api/check_auth.php)
- JavaScript makes fetch requests to PHP endpoints

**Resolution Needed:** Update description to accurately reflect the JavaScript + PHP API architecture.

---

## 5. Form Validation Technology Conflicts

### Conflict 5: Form Validation Libraries
**Documentation Claims (Section 3.6):**
- "libraries such as Formik and Yup are used to validate required fields"

**Actual Implementation:**
- Uses Bootstrap's built-in form validation classes
- Custom JavaScript validation in individual .js files
- No Formik or Yup libraries found

**Resolution Needed:** Remove references to Formik and Yup. Update to reflect Bootstrap validation + custom JavaScript.

---

## 6. Missing Appendices References

### Conflict 6: Referenced but Missing Content
**Documentation References:**
- Section 2.1.2: "8.1 Appendix A.1 – Online Survey Form and Results"
- Section 4.2.1: "8.2 Appendix B"
- Section 4.2.2: "8.3 Appendix C – Legal Query Submission Code"
- Section 4.2.3: "8.4 Appendix D – Real-Time Advisor Response Code"
- Section 4.2.4: "8.5 Appendix E – Admin Panel and Advisor Verification Module"
- Section 4.2.5: "8.6 Appendix F – Ratings and Feedback System"

**Status:** All appendices are referenced but not present in the document.

**Resolution Needed:** Either create these appendices or remove all references to them.

---

## 7. Development Timeline Inconsistencies

### Conflict 7: Project Timeline
**Documentation Claims:**
- Section 2.6: "The development timeframe is approximately 12 weeks"
- Section 2.8: "Figure 2.8 below illustrates the detailed work schedule"

**Status:** Timeline mentioned but detailed schedule not provided.

**Resolution Needed:** Provide the actual work schedule or remove the reference to Figure 2.8.

---

## 8. Technology Justification Conflicts

### Conflict 8: Technology Choices vs Implementation
**Documentation Claims (Section 2.7):**
- Justifies technology choices based on "familiarity, application suitability, hosting compatibility, and community support"
- Mentions Python for "secure APIs and backend logic"

**Actual Implementation:**
- Python only used for WebSocket server (real-time messaging)
- PHP handles all other backend logic and APIs

**Resolution Needed:** Update technology justification to accurately reflect Python's role for WebSocket server only, not general backend logic.

---

## Summary of Required Actions

1. **Remove all React/Node.js references** - Update to reflect Bootstrap + vanilla JavaScript frontend
2. **Clarify backend architecture** - PHP for APIs, Python for WebSocket server
3. **Remove Formik/Yup references** - Update to reflect Bootstrap + custom JavaScript validation
4. **Create missing appendices** or remove all references to them
5. **Provide project timeline** or remove Figure 2.8 reference
6. **Update technology justification** to match actual implementation
7. **Verify all API endpoint references** match actual files in api/ directory

These conflicts need to be resolved to ensure documentation accuracy and consistency with the actual project implementation. 
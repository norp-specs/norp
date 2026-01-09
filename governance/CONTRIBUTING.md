# Contributing to NORP

Thank you for your interest in contributing to NORP (NeuraScope Orchestration Reference Patterns)!

---

## How to Contribute

### 1. Propose New Specifications

To propose a new NORP:

1. **Open an issue** describing the problem your spec addresses
2. **Provide evidence** from production systems (code, incidents, metrics)
3. **Draft the specification** following the template in `specs/TEMPLATE.md`
4. **Submit a Pull Request** with your draft

### 2. Improve Existing Specifications

To improve an existing NORP:

1. **Open an issue** describing the ambiguity or missing detail
2. **Reference specific sections** (e.g., "NORP-001 Section 5.4 is unclear on...")
3. **Propose concrete changes** (diff or rewritten text)
4. **Submit a Pull Request**

### 3. Contribute Reference Implementations

To contribute code:

1. Ensure your implementation **passes all compliance tests**
2. Follow language-specific style guides (PSR-12 for PHP, PEP 8 for Python, etc.)
3. Include **unit tests** and **integration tests**
4. Document **how to run tests** in a README
5. Submit under **MIT License**

### 4. Report Issues

Found a problem? Open an issue with:
- **Spec number** (e.g., NORP-001)
- **Section reference** (e.g., Section 5.2.1)
- **Description** of the issue (ambiguity, contradiction, missing case)
- **Proposed solution** (optional but helpful)

---

## Contribution Guidelines

### Standards for Specifications

All NORP specs MUST:
- Follow **RFC 2119** keywords (MUST, SHOULD, MAY)
- Include **normative requirements** (what implementations must do)
- Include **compliance tests** (how to verify conformance)
- Be **technology-agnostic** (no vendor lock-in)

All NORP specs SHOULD:
- Include **motivation** (why this spec exists)
- Include **examples** (both normative and non-normative)
- Include **security considerations**
- Reference **existing code** in production

### Standards for Code

All reference implementations MUST:
- Pass **100% of compliance tests** for the relevant NORP
- Include **inline documentation** (docstrings, comments)
- Follow **language conventions** (PSR-12, PEP 8, etc.)
- Be licensed under **MIT**

All reference implementations SHOULD:
- Achieve **>80% code coverage**
- Include **performance benchmarks**
- Avoid **external dependencies** where possible

---

## Review Process

### Spec Review Timeline

1. **Draft submission** → Assigned to reviewer within 7 days
2. **First review** → Feedback within 14 days
3. **Revisions** → Author updates based on feedback
4. **Second review** → Final approval or additional feedback within 7 days
5. **Merge** → Spec published in `specs/`

### Approval Criteria

A spec is approved when:
- ✅ All normative requirements are clear and testable
- ✅ Compliance tests are complete and executable
- ✅ At least one reference implementation exists
- ✅ Two reviewers have approved

---

## Code of Conduct

- **Be respectful** in all interactions
- **Focus on technical merit** (not personalities)
- **Assume good intent** (ask questions before criticizing)
- **Prioritize production evidence** over theoretical arguments

---

## Attribution

Contributors will be listed in:
- The spec itself (in "Authors" or "Contributors" section)
- The project README
- Release notes for major versions

---

## Questions?

- Open a **GitHub Discussion** for general questions
- Open an **Issue** for specific problems
- Email **norp@neurascope.ai** for private inquiries

---

Thank you for helping build open standards for AI orchestration! 🚀

**NORP Working Group**

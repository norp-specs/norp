# Contributing to NORP

Thank you for your interest in contributing to **NORP** (NeuraScope Orchestration Reference Patterns)!

NORP is an open standard for production-grade AI orchestration systems. Your contributions help make AI systems safer, more predictable, and more reliable.

---

## 📝 Types of Contributions

We welcome contributions in several forms:

### 1. **New Language Implementations**
Port NORP reference implementations to new languages (Go, Rust, Java, C#, etc.)

### 2. **Specification Improvements**
- Fix typos or clarify ambiguous sections
- Add examples or diagrams
- Propose new normative requirements

### 3. **Use Cases and Examples**
- Industry-specific examples (healthcare, finance, maritime, SaaS)
- Production deployment case studies
- Integration guides for popular frameworks

### 4. **Bug Reports**
- Issues in specifications (contradictions, missing details)
- Bugs in reference implementations
- Documentation errors

### 5. **Test Suite Enhancements**
- Additional compliance tests
- Edge case coverage
- Performance benchmarks

---

## 🚀 Contribution Process

### Step 1: Fork and Clone

```bash
# Fork the repo on GitHub, then clone your fork
git clone https://github.com/YOUR_USERNAME/norp-specs.git
cd norp
```

### Step 2: Create a Branch

```bash
git checkout -b feature/my-contribution
# or
git checkout -b fix/issue-123
```

### Step 3: Make Changes

- Follow existing file structure and naming conventions
- Maintain consistency with existing specifications
- Add tests for new code
- Update `CHANGELOG.md` for significant changes

### Step 4: Commit

```bash
git add .
git commit -m "Add: Brief description of change

- Detailed explanation
- Reference issue if applicable (#123)

Implements: NORP-XXX (if relevant)"
```

### Step 5: Push and Create Pull Request

```bash
git push origin feature/my-contribution
```

Then open a Pull Request on GitHub.

---

## 📋 Contribution Guidelines

### For Specifications

All NORP specs MUST:
- Follow **RFC 2119** keywords (MUST, SHOULD, MAY)
- Include **normative requirements** (Section 5)
- Include **compliance tests** (Section 9)
- Be **technology-agnostic** (no vendor lock-in)
- Include **license header** (CC BY 4.0)

All NORP specs SHOULD:
- Include **motivation** (Section 2)
- Include **examples** (Appendix A)
- Include **security considerations** (Section 10)
- Reference **production code** when possible

### For Reference Implementations

All reference implementations MUST:
- Pass **100% of compliance tests** for the relevant NORP
- Include **inline documentation** (docstrings, comments)
- Follow **language conventions** (PEP 8 for Python, PSR-12 for PHP, etc.)
- Be licensed under **MIT**
- Have **zero external dependencies** (or document minimal dependencies)

All reference implementations SHOULD:
- Achieve **>80% code coverage**
- Include **usage examples**
- Include **performance benchmarks**

### For Examples and Use Cases

Examples SHOULD:
- Be **realistic** (based on real-world scenarios)
- Include **expected outputs** (validation results, errors, costs)
- Reference **specific NORP patterns** used
- Be licensed under **CC0** (public domain)

---

## 🔍 Review Process

### Timeline

1. **Pull Request submitted** → Assigned to reviewer within **7 days**
2. **First review** → Feedback within **14 days**
3. **Revisions** → Author updates based on feedback
4. **Second review** → Final approval or additional feedback within **7 days**
5. **Merge** → Changes merged to `main`

### Approval Criteria

A contribution is approved when:
- ✅ All normative requirements are clear and testable (for specs)
- ✅ Code passes all compliance tests (for implementations)
- ✅ Documentation is complete and accurate
- ✅ At least **two reviewers** have approved

---

## 🧪 Testing Your Contribution

### For Specifications

Before submitting:
- [ ] All sections are complete (Abstract, Motivation, Scope, etc.)
- [ ] At least 4 compliance tests defined
- [ ] Examples included (code or workflows)
- [ ] Cross-references to other NORP specs verified
- [ ] License header present

### For Code

Before submitting:
- [ ] All compliance tests pass
- [ ] Code follows language conventions
- [ ] Documentation is complete
- [ ] Example usage provided
- [ ] No external dependencies (or justified)

Run tests:

```bash
# Python
cd reference-implementations/python
python -m pytest tests/

# TypeScript
cd reference-implementations/typescript
npm test

# PHP
cd reference-implementations/php
./vendor/bin/phpunit
```

---

## 💬 Communication

### Questions or Discussions

- **GitHub Discussions**: https://github.com/norp-specs/norp/discussions
- **Email**: norp@neurascope.ai

### Bug Reports or Feature Requests

- **GitHub Issues**: https://github.com/norp-specs/norp/issues

### Real-Time Chat

- Join our community (coming soon)

---

## 📜 Code of Conduct

All contributors must follow our [Code of Conduct](CODE_OF_CONDUCT.md).

**In short**:
- Be respectful and constructive
- Focus on technical merit
- Assume good intent
- Prioritize production evidence over theoretical arguments

---

## 🏆 Recognition

Contributors will be recognized in:
- The specification itself (Authors or Contributors section)
- `CHANGELOG.md` for each release
- GitHub contributors page
- Annual NORP report

---

## 🎯 Priority Areas for Contribution

We especially welcome contributions in:

1. **New language implementations** (Go, Rust, Java, C#)
2. **Industry-specific examples** (healthcare, finance, maritime)
3. **Integration guides** (LangChain, n8n, Flowise, Airflow)
4. **Performance benchmarks** (large-scale deployments)
5. **Tooling** (linters, validators, CI/CD integrations)

---

## 📚 Resources

- **Specifications**: [specs/](specs/)
- **Reference Implementations**: [reference-implementations/](reference-implementations/)
- **Compliance Tests**: [compliance-tests/](compliance-tests/)
- **Examples**: [examples/](examples/)
- **Roadmap**: [governance/ROADMAP.md](governance/ROADMAP.md)

---

## ❓ FAQ for Contributors

**Q: Can I propose a new NORP specification (NORP-008, etc.)?**
Yes! Open an issue first to discuss the scope and rationale.

**Q: Do I need to implement all 7 NORP specs to contribute code?**
No. You can implement a subset (e.g., just NORP-001 + NORP-004).

**Q: Can I contribute proprietary implementations?**
Reference implementations must be MIT licensed. You can mention proprietary implementations in case studies.

**Q: How do I become a NORP reviewer?**
Contribute high-quality PRs consistently, then contact norp@neurascope.ai.

---

Thank you for helping build open standards for AI orchestration! 🚀

**NORP Working Group**
**© 2026 NeuraScope CONVERWAY**

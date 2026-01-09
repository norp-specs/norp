# NORP Roadmap

## Vision

Establish NORP as the **de facto standard** for production-grade AI orchestration systems, focusing on security, reliability, and cost control.

---

## Phase 1: Foundation (Q1 2026) - **CURRENT**

### Goals
- Publish 7 core NORP specifications (001-007)
- Extract reference implementations from NeuraScope production code
- Build compliance test suite

### Deliverables
- ✅ NORP-001: Pre-Execution Validation Pipeline (v1.1 Draft)
- 🔄 NORP-002: Multi-Tenant Resource Isolation
- 🔄 NORP-003: Immutable Pipeline with DTOs
- 🔄 NORP-004: Cycle Detection in Directed Graphs
- 🔄 NORP-005: Topological Sorting for Execution Order
- 🔄 NORP-006: Resource Pooling with Context Isolation
- 🔄 NORP-007: Cost Estimation Pre-Execution
- ⏳ PHP reference implementations (extracted from NeuraScope)
- ⏳ Compliance test suite v1.0

### Success Metrics
- 7 specs published
- 1 complete reference implementation (PHP)
- 50+ compliance tests

---

## Phase 2: Multi-Language Support (Q2 2026)

### Goals
- Port reference implementations to TypeScript and Python
- Enable broader adoption across ecosystems

### Deliverables
- TypeScript reference implementation (Node.js compatible)
- Python reference implementation (compatible with LangChain, AutoGen)
- Cross-language compliance testing

### Success Metrics
- 3 complete reference implementations
- 100% test parity across languages
- 5+ external contributors

---

## Phase 3: Community Adoption (Q3 2026)

### Goals
- Drive adoption beyond NeuraScope
- Establish certification program
- Build ecosystem

### Deliverables
- NORP-compliant certification badge
- Integration guides for popular frameworks (LangChain, n8n, Zapier)
- Case studies from early adopters
- Conference presentations (AI Engineering Summit, etc.)

### Success Metrics
- 10+ external adopters
- 3+ certified implementations
- 500+ GitHub stars

---

## Phase 4: Advanced Specifications (Q4 2026)

### Goals
- Extend NORP to cover advanced use cases
- Address feedback from production deployments

### Planned Specifications
- **NORP-008**: Distributed Execution Guarantees
- **NORP-009**: Transactional Rollback Semantics
- **NORP-010**: Observability and Audit Standards
- **NORP-011**: Dynamic Workflow Generation
- **NORP-012**: Multi-Model Orchestration
- **NORP-013**: Streaming Execution Patterns

### Success Metrics
- 6 new specifications published
- 2+ implementations supporting advanced specs
- Production deployments at scale (>1M workflows/month)

---

## Phase 5: Standardization (2027)

### Goals
- Submit NORP to standards body (IETF, W3C, or equivalent)
- Achieve industry-wide recognition

### Deliverables
- Formal standardization proposal
- Interoperability test suite
- Reference architecture documentation
- Industry partnerships (AWS, Microsoft, Google, Anthropic)

### Success Metrics
- Standards body acceptance
- 50+ certified implementations
- Integration in major cloud platforms

---

## Open Questions

### Q1: Should NORP define a wire protocol?
**Status**: Under discussion

**Options**:
- A) Focus only on internal architecture (current approach)
- B) Define JSON-RPC or gRPC protocol for distributed execution
- C) Defer to existing protocols (MCP, OpenAI Agents)

**Decision by**: End of Phase 1

---

### Q2: How to handle vendor-specific extensions?
**Status**: Under discussion

**Proposal**: Allow "NORP-X-EXT-Y" extension specs that are optional but documented.

**Example**: `NORP-001-EXT-AWS` for AWS-specific validation optimizations.

**Decision by**: Phase 2

---

### Q3: Should reference implementations be in a separate repo?
**Status**: Under discussion

**Options**:
- A) Keep in NORP repo (current)
- B) Split into language-specific repos (e.g., `norp-php`, `norp-ts`)
- C) Publish as packages (Composer, npm, PyPI) with separate repos

**Decision by**: Phase 2

---

## Contributing to Roadmap

To propose roadmap changes:
1. Open an issue labeled `roadmap`
2. Describe the problem and proposed solution
3. Provide evidence (production needs, user requests, market gaps)
4. Participate in discussion

---

**Last updated**: 2026-01-09
**Next review**: 2026-04-01

# Sprint 3 – Client Clarifications

## 1. Purpose

This document summarizes the client's responses to the Wayback Public Library (WPL) development team's requirements questions for Sprint 3 and future development.

The purpose is to clarify expected functionality, establish business rules and priorities, identify technical dependencies, and provide a consistent foundation for user stories, acceptance criteria, activity diagrams, data models, wireframes, implementation, and testing.

The client has emphasized that delivering a smaller set of fully functional, thoroughly tested features is preferable to implementing incomplete versions of numerous features. Final sprint commitments will therefore be determined through team capacity planning and client review.

---

## 2. Automated Due Date and Overdue Notices

**Q1. When should patrons receive reminders before items are due?**

**Client response:** Books and movies should appear on a reminder list three days before their due dates. Patrons should be encouraged to return or renew their items.

**Decision:** Support identification of books and movies approaching their due dates, using a three-day advance reminder window.

**Q2. How frequently should overdue notices be generated?**

**Client response:** Once per week.

**Decision:** Provide a weekly overdue report or notice list for library staff.

**Q3. How should reminders be delivered?**

**Client response:** The system should generate an internal list or report identifying overdue items, responsible patrons, and applicable amounts. Library staff will decide whether and how to contact patrons.

**Decision:** Prioritize staff-facing reporting rather than automated email or SMS integration.

**Q4. What information should reminders contain?**

**Client response:** Include the patron's name, item information, and due date. A renewal link would be desirable, but a patron-facing portal is not currently available.

**Decision:** Display patron identification, item details, and due dates. Direct patron renewal links are outside the current implementation scope.

**Q5. Should reminders be sent automatically?**

**Client response:** Staff should review the generated list and determine what action to take.

**Decision:** Generate staff-accessible reminder and overdue reports. Sending external notifications remains a staff-directed activity.

**Open consideration:** The client did not specify a pre-due reminder period for periodicals.

---

## 3. Patron Ratings and Reviews

**Q6. What type of rating and review system should WPL support?**

**Client response:** A five-star rating system with optional written reviews. Because there is no patron portal, reviews may be collected on paper and entered periodically by library personnel.

**Decision:** Support staff entry of patron ratings and written reviews, potentially through batch entry.

**Q7. Who may submit reviews?**

**Client response:** Patrons should only review items they have previously borrowed.

**Decision:** Validate that a patron has a historical checkout associated with the reviewed catalog item.

**Q8. Should reviews be associated with titles or physical copies?**

**Client response:** Reviews should be associated with the catalog item rather than an individual copy.

**Decision:** Store reviews against the catalog item record.

**Q9. Who should be able to view reviews?**

**Client response:** Reviews should be available for broader viewing and should support future recommendation functionality.

**Decision:** Design review data for future public-facing use, while the current application remains primarily staff-operated.

**Q10. Should librarians moderate reviews?**

**Client response:** Yes.

**Decision:** Provide librarians with the ability to review, modify, or remove inappropriate content.

**Implementation consideration:** Ratings and reviews are desirable enhancements but must be prioritized against more essential circulation and reporting functionality.

---

## 4. AI-Based Recommendations

**Q11. What should the recommendation system accomplish?**

**Client response:** Recommend items related to a patron's previous borrowing history, interests, ratings, and reviews. For example, patrons who have borrowed several related books could receive suggestions for similar titles.

**Decision:** Recommendations should consider historical loans and review information when available.

**Q12. What information should recommendations use?**

**Client response:** A combination of previous loans, ratings, and reviews.

**Decision:** These data sources should inform the recommendation process.

**Q13. Should recommendations include items outside WPL's current collection?**

**Client response:** Yes, recommendations may include items beyond the existing inventory.

**Decision:** Support current-collection recommendations as a possible initial implementation, with external-item suggestions as an enhancement.

**Q14. Should recommendations be automatic or requested?**

**Client response:** Staff or patrons should be able to request recommendations.

**Decision:** Prioritize an on-demand recommendation workflow.

**Q15. How should patrons access recommendations?**

**Client response:** A librarian-operated interface is sufficient for the initial implementation. A future kiosk could allow patrons to access recommendations themselves.

**Decision:** The initial system may allow staff to select a patron and request suggestions based on the patron's recorded history.

**Q16. Are there additional privacy constraints?**

**Client response:** The client did not identify additional restrictions and expects staff to access the relevant library data.

**Decision:** Use the existing staff-operated model for requirements planning, while limiting access and use of patron borrowing histories to legitimate library purposes.

**Implementation consideration:** A recommendation prototype should only be committed if the team can implement and meaningfully evaluate it within available capacity.

---

## 5. Administrative Dashboard and Trend Analysis

**Q17. Which indicators should the dashboard display?**

**Client response:** Trends in circulation transactions, patron counts, collection size, acquisition costs, and fines. The client wants to understand how these figures change over time.

**Decision:** Prioritize a dashboard displaying historical operational and financial trends.

**Q18. Should the dashboard cover individual branches or WPL as a whole?**

**Client response:** Ideally both, with the ability to filter by branch. If necessary, begin with library-wide reporting.

**Decision:** Library-wide reporting is the minimum scope. Branch-specific views are desirable.

**Q19. What reporting periods should be supported?**

**Client response:** At least weekly.

**Decision:** Support weekly trends, with additional time ranges if feasible.

**Q20. Should dashboard results support filters?**

**Client response:** Yes, including branch, item type, and date range.

**Decision:** Provide filters where the underlying data supports them.

**Q21. What financial information is available?**

**Client response:** Item acquisition costs and library fines. Broader accounting information is not currently recorded.

**Decision:** Limit financial reporting to available collection-cost and fine information. Do not imply that the system provides complete organizational financial statements.

**Implementation consideration:** The existing Item Statuses interface may serve as a foundation for operational reporting, but trend analysis may require separate summary queries and visualizations.

---

## 6. Traditional and Natural-Language Search

**Q22. What information should librarians be able to search?**

**Client response:** Primarily catalog items and patrons, including associated circulation information such as current loans, due dates, and fines.

**Decision:** Prioritize item and patron search with relevant linked records.

**Q23. Which search fields should be supported?**

**Client response:** Searches should not be limited to identifiers. Item titles, names, and other identifying information should be supported.

**Decision:** Provide multiple searchable fields where practical.

**Q24. Should search support partial matches and filters?**

**Client response:** Yes. The client values flexible matching, filtering, and sorting.

**Decision:** Support partial keyword matching, practical filtering, and relevant sorting.

**Q25. What are the expectations for NLP-based search?**

**Client response:** Natural-language queries would be desirable, but the most important requirement is a forgiving search experience that tolerates partial names, incomplete titles, and spelling mistakes.

**Decision:** Prioritize improved keyword and approximate matching. Full conversational natural-language interpretation is not required for the initial implementation.

**Q26. Should search be able to modify records?**

**Client response:** No. Search should retrieve information only.

**Decision:** Search functionality will be read-only.

---

## 7. Library Room Reservations

**Q27. What rooms should be available for reservation?**

**Client response:** Assume each library branch has one reservable community room, available during library operating hours.

**Decision:** Model one community room per branch for the initial implementation.

**Q28. Who can reserve rooms?**

**Client response:** A registered patron with a current membership must be responsible for the reservation. The room may be used by additional attendees.

**Decision:** Associate each reservation with one eligible patron.

**Q29. What information must be recorded?**

**Client response:** Meeting or event title, date, time, and expected attendance.

**Decision:** Record the responsible patron, room, event title, start and end times, and attendee count.

**Q30. Are there time or capacity limits?**

**Client response:** A maximum of 15 attendees. Reservations may last an entire operating day, and no advance-booking limit was specified.

**Decision:** Enforce a maximum attendance of 15 and require reservations to fall within operating hours.

**Q31. Do reservations require separate approval?**

**Client response:** Librarians will enter reservations into the system. Additional approval is unnecessary.

**Decision:** Confirm valid, nonconflicting reservations when saved by staff.

**Q32. How should cancellations and conflicts be handled?**

**Client response:** Cancellations should be supported. Existing reservations must prevent overlapping bookings, and staff should select another time when a conflict occurs.

**Decision:** Support cancellation, reservation listings, and overlap prevention.

**Open consideration:** Recurring reservation creation was not explicitly confirmed as a requirement.

---

## 8. Community Purchase Suggestions

**Q33. Who may suggest items for purchase?**

**Client response:** Anyone, including nonregistered community members. Suggestions may initially be submitted through paper forms and entered by library staff.

**Decision:** Suggestions do not require a Patron record.

**Q34. How should suggestions be represented?**

**Client response:** Suggested items should be represented in the system even before they have been acquired. The library should be able to identify items being considered for purchase and eventually activate acquired items.

**Decision:** Support distinguishing suggested or planned acquisitions from items already owned by WPL.

**Q35. What information should be recorded?**

**Client response:** Use information similar to normal catalog item entry, while allowing unavailable fields, such as acquisition cost or date, to remain unspecified.

**Decision:** Allow incomplete acquisition information for proposed items.

**Q36. How should suggestions be approved or rejected?**

**Client response:** Formal review may take place outside the system using paper-based procedures.

**Decision:** A comprehensive digital suggestion-approval workflow is not required.

**Q37. Should patrons vote on suggestions?**

**Client response:** No.

**Decision:** Voting functionality is out of scope.

**Q38. Should patrons receive notifications?**

**Client response:** No notification functionality is required. A future reservation or hold for an anticipated item could be desirable.

**Decision:** Notifications and advance holds are optional future enhancements.

**Implementation consideration:** Purchase suggestions are lower priority than circulation stability, reporting, and search.

---

## 9. Sprint Priorities and Feasibility

**Q39. Which new features provide the greatest immediate value?**

**Client response:** The dashboard and improved search are the highest priorities. A well-designed dashboard may also support overdue and reminder reporting. Room reservations and patron reviews are additional desirable enhancements.

**Decision:** Prioritize dashboard/reporting and search during sprint planning, while accounting for the effort required to complete existing functionality.

**Q40. Should the team deliver many prototypes or fewer complete features?**

**Client response:** Fewer fully functional features.

**Decision:** Limit sprint commitments to work that can be modeled, developed, integrated, tested, and demonstrated.

**Q41. Who is expected to operate the system?**

**Client response:** Primarily library staff. Staff are assumed to have access to the necessary administrative functionality.

**Decision:** Maintain the staff-operated application model for this sprint. A separate patron portal is not required.

**Q42. Do maintenance fixes require new user stories?**

**Client response:** New stories should be created where needed. Patron membership renewal and fine evaluation/payment are examples of workflows that may require user stories, activity diagrams, and other supporting models.

**Decision:** Retain existing story identifiers where available, refine incomplete acceptance criteria, and create new stories only for distinct functionality.

**Q43. When should the client review proposed designs?**

**Client response:** The team should show proposed user stories, models, and interface designs before implementing complex features.

**Decision:** Obtain client feedback on the planned workflow and interfaces before major development commitments.

---

## 10. Overall Requirements and Development Implications

Sprint 3 planning should reflect the following:

1. Complete and validate existing essential circulation functionality before expanding into lower-priority features.
2. Maintain clear traceability between user stories, acceptance criteria, diagrams, wireframes, data models, implementation, and test evidence.
3. Prioritize improved administrative search and historical reporting.
4. Incorporate overdue and upcoming-due reporting into the dashboard where feasible.
5. Model new entities only when needed to support selected stories.
6. Preserve the staff-operated system design rather than introducing an unsupported patron portal.
7. Review complex workflows and models with the client before implementation.
8. Keep the product backlog and sprint task board current throughout development.
9. Prefer a small number of complete, demonstrable stories over a broad collection of unfinished features.

## 11. Remaining Decisions

The following points should be resolved during story refinement or client review:

- Determine the final set of stories the team can reasonably commit to for Sprint 3.
- Confirm which operational dashboard metrics constitute the minimum acceptable deliverable.
- Determine the minimum acceptable approximate-search behavior, including how spelling mistakes should be handled.
- Confirm specific room operating hours if room reservations are selected.
- Define whether purchase suggestions should be stored separately from owned catalog items if that feature is selected.
- Determine whether ratings/reviews and AI recommendations are feasible within available development capacity.
- Confirm simplified check-in, routing, and reservation workflows in response to Sprint 2 feedback.

These clarifications represent the client's guidance. They do not automatically commit the team to implementing every proposed enhancement during Sprint 3.

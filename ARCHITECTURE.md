# Laravel Activity Feed - Architecture

## System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        User Application                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Laravel Activity Feed                         │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │                   Helper Function                          │ │
│  │                     feed()                                 │ │
│  └────────────────────────┬───────────────────────────────────┘ │
│                           │                                      │
│  ┌────────────────────────▼───────────────────────────────────┐ │
│  │               FeedItemBuilder                              │ │
│  │  • withAction()                                            │ │
│  │  • withTemplate()                                          │ │
│  │  • causedBy() / performedOn()                              │ │
│  │  • withProperties()                                        │ │
│  │  • log() ──────────────────────┐                           │ │
│  └────────────────────────────────┼───────────────────────────┘ │
│                                   │                              │
│  ┌────────────────────────────────▼───────────────────────────┐ │
│  │                    FeedItem Model                          │ │
│  │  • action                                                  │ │
│  │  • description_template                                    │ │
│  │  • properties (JSON)                                       │ │
│  │  • occurred_at                                             │ │
│  │  • renderDescription() ────┐                               │ │
│  └────────────┬───────────────┼───────────────────────────────┘ │
│               │               │                                  │
│               │      ┌────────▼────────────────────────────┐    │
│               │      │   DescriptionRenderer               │    │
│               │      │  • Resolve entity placeholders      │    │
│               │      │  • Apply "You" transformation       │    │
│               │      │  • Format property values           │    │
│               │      │  • Cache results                    │    │
│               │      └─────────────────────────────────────┘    │
│               │                                                  │
│               ▼                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │              FeedItemEntity Model                          │ │
│  │  • feed_item_id                                            │ │
│  │  • entity_type (polymorphic)                               │ │
│  │  • entity_id (polymorphic)                                 │ │
│  │  • role (actor, subject, target, etc.)                     │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │                    HasFeed Trait                           │ │
│  │  • feedItems()                                             │ │
│  │  • feedItemsAsSubject()                                    │ │
│  │  • feedItemsAsActor()                                      │ │
│  │  • createFeedItem()                                        │ │
│  │  • logActivity()                                           │ │
│  └────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                         Database                                │
│                                                                  │
│  ┌──────────────────────┐    ┌────────────────────────────────┐│
│  │   feed_items         │    │   feed_item_entities           ││
│  ├──────────────────────┤    ├────────────────────────────────┤│
│  │ id                   │◄───┤ feed_item_id (FK)              ││
│  │ action               │    │ entity_type                    ││
│  │ description_template │    │ entity_id                      ││
│  │ properties           │    │ role                           ││
│  │ occurred_at          │    │                                ││
│  │ timestamps           │    │ Indexes:                       ││
│  │                      │    │ • (entity_type, entity_id)     ││
│  │ Indexes:             │    │ • (feed_item_id, role)         ││
│  │ • action             │    └────────────┬───────────────────┘│
│  │ • occurred_at        │                 │                    │
│  │ • (occurred_at,      │                 │  Polymorphic       │
│  │    action)           │                 │  Relationship      │
│  └──────────────────────┘                 ▼                    │
│                                ┌─────────────────────┐         │
│                                │   Your Models       │         │
│                                │  • User             │         │
│                                │  • Order            │         │
│                                │  • Post             │         │
│                                │  • etc.             │         │
│                                └─────────────────────┘         │
└─────────────────────────────────────────────────────────────────┘
```

## Data Flow

### 1. Creating a Feed Item

```
User Action
    │
    ▼
feed() helper
    │
    ▼
FeedItemBuilder
 • Collects action
 • Collects template
 • Collects entities (with roles)
 • Collects properties
    │
    ▼
log() method
    │
    ├──► Create FeedItem record
    │     • action
    │     • description_template
    │     • properties (JSON)
    │     • occurred_at
    │
    └──► Create FeedItemEntity records
          • One for each entity
          • Stores role (actor, subject, etc.)
          • Polymorphic relation to actual entity
```

### 2. Querying Feed Items

```
Query Request
    │
    ▼
FeedItem::forEntity($order, 'subject')
    │
    ▼
Query Builder
 • Apply scopes (ofAction, inPeriod, etc.)
 • Add eager loading (entities.entity)
 • Apply ordering (latestOccurred)
    │
    ▼
Database Query
 • Uses composite indexes for performance
 • Joins feed_item_entities
 • Loads related entities
    │
    ▼
Collection of FeedItems
```

### 3. Rendering Descriptions

```
FeedItem
    │
    ▼
renderDescription($viewer)
    │
    ├──► Check Cache
    │     • Key: feed_item:{id}:rendered:{viewer_id}
    │     • TTL: 15 minutes (configurable)
    │
    └──► [Cache Miss] ──► DescriptionRenderer
                           │
                           ▼
                    Load entities by role
                           │
                           ▼
                    Resolve placeholders:
                     • {actor} ──► "You" or entity name
                     • {subject} ──► entity name
                     • {amount} ──► property value
                           │
                           ▼
                    Replace in template
                           │
                           ▼
                    Cache result
                           │
                           ▼
                    Return rendered string
```

## Entity Resolution Flow

```
Template: "{actor} approved {subject} for {amount}"
    │
    ▼
┌─────────────────────────────────────────────┐
│ Step 1: Load entities by role               │
│                                              │
│ entities.where('role', 'actor')    ──► User │
│ entities.where('role', 'subject') ──► Order │
└───────────────┬─────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────────┐
│ Step 2: Resolve entity names                │
│                                              │
│ {actor}   ──► Is viewer? ──► "You"          │
│           │                                  │
│           └─► Not viewer ──► "John Doe"     │
│                                              │
│ {subject} ──► Order::getFeedDisplayName()   │
│           ──► "PO-6402"                     │
└───────────────┬─────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────────┐
│ Step 3: Resolve properties                  │
│                                              │
│ {amount} ──► properties['amount']           │
│          ──► "$500"                         │
└───────────────┬─────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────────┐
│ Step 4: Replace in template                 │
│                                              │
│ Result: "You approved PO-6402 for $500"     │
└─────────────────────────────────────────────┘
```

## Query Optimization Strategy

```
┌─────────────────────────────────────────────────────────┐
│              Query Performance Flow                      │
└─────────────────────────────────────────────────────────┘

1. Apply Scopes (filter early)
   ├─ ofAction('approved')
   ├─ inPeriod(startDate, endDate)
   └─ forEntity($order, 'subject')
        │
        ▼
2. Use Composite Indexes
   ├─ (occurred_at, action) for time-based queries
   ├─ (entity_type, entity_id, role) for entity lookups
   └─ (feed_item_id, role) for relationship queries
        │
        ▼
3. Eager Load Relations (prevent N+1)
   └─ with('entities.entity')
        │
        ▼
4. Paginate Efficiently
   ├─ cursorPaginate(20) for large datasets
   └─ Better than offset pagination
        │
        ▼
5. Cache Rendered Descriptions
   └─ Per viewer, 15 min TTL
```

## Caching Architecture

```
┌─────────────────────────────────────────────────────────┐
│                  Cache Strategy                         │
└─────────────────────────────────────────────────────────┘

Level 1: Description Caching
  ┌──────────────────────────────────────┐
  │ Key: feed_item:{id}:rendered:{viewer}│
  │ TTL: 15 minutes (configurable)       │
  │ Invalidate: On update/delete         │
  └──────────────────────────────────────┘

Level 2: Query Caching (Optional - User implemented)
  ┌──────────────────────────────────────┐
  │ Key: feed:order:{id}                 │
  │ TTL: 5-10 minutes                    │
  │ Invalidate: On new feed item         │
  └──────────────────────────────────────┘

Level 3: Database Query Cache (Built-in Laravel)
  ┌──────────────────────────────────────┐
  │ MySQL Query Cache                    │
  │ Automatic for identical queries      │
  └──────────────────────────────────────┘
```

## Scalability Considerations

```
┌─────────────────────────────────────────────────────────┐
│            Traffic Level Strategies                     │
└─────────────────────────────────────────────────────────┘

Low Traffic (< 1K items/day)
  • Basic setup works fine
  • Default caching sufficient
  • No special optimization needed

Medium Traffic (1K-100K/day) ← Current Design Target
  • Use composite indexes (included)
  • Enable description caching (default)
  • Eager load relationships
  • Consider read replicas for heavy read

High Traffic (100K-1M+/day)
  • Partition tables by date
  • Separate read/write databases
  • Implement archival strategy
  • Consider dedicated cache server (Redis)

Very High Traffic (1M+/day)
  • Time-series database for feeds
  • Event streaming (Kafka)
  • Separate microservice
  • Real-time aggregation
```

## Extension Points

```
┌─────────────────────────────────────────────────────────┐
│         Where to Customize/Extend                       │
└─────────────────────────────────────────────────────────┘

1. Custom Entity Roles
   └─ Add custom roles beyond actor/subject
      Example: 'reviewer', 'approver_level_2'

2. Custom Display Names
   └─ Override getFeedDisplayName() in models
      Example: Return formatted name with title

3. Custom Description Logic
   └─ Extend DescriptionRenderer
      Example: Add markdown support, emoji

4. Custom Actions
   └─ Add to config/feed.php actions array
      Example: 'exported', 'imported', 'merged'

5. Custom Cleanup Logic
   └─ Extend CleanupFeedItemsCommand
      Example: Archive instead of delete

6. Custom Query Scopes
   └─ Add to FeedItem model
      Example: scopeForTeam(), scopePublic()

7. Event Listeners
   └─ Listen to model events
      Example: Auto-create feed on model changes

8. Notifications Integration
   └─ Trigger notifications from feed items
      Example: Notify mentioned users

9. Broadcasting Integration
   └─ Broadcast new feed items
      Example: Real-time feed updates via WebSockets
```

## Security Considerations

```
┌─────────────────────────────────────────────────────────┐
│              Security Best Practices                    │
└─────────────────────────────────────────────────────────┘

1. Authorization
   ├─ Check if viewer can see feed item
   ├─ Check if viewer can see related entities
   └─ Implement policies for feed access

2. Sanitization
   ├─ Escape output in views
   ├─ Validate properties input
   └─ Prevent XSS in descriptions

3. Privacy
   ├─ Don't log sensitive data in properties
   ├─ Consider encryption for sensitive fields
   └─ Respect user privacy settings

4. Performance Limits
   ├─ Rate limit feed creation
   ├─ Limit query result size
   └─ Prevent DoS via expensive queries

5. Data Retention
   ├─ Follow GDPR/privacy regulations
   ├─ Implement user data deletion
   └─ Auto-cleanup old data
```

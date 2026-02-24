# IETI School Activities Scheduling and Inventory Management System
## Dataflow Diagram & Flowcharts

---

## 1. System Dataflow Diagram

Shows how data moves between users, the web application, Laravel backend, and the database.

```mermaid
flowchart TB
    subgraph External["External Users"]
        Admin["Admin"]
        Heads["College/Senior/Junior Heads"]
        Staff["Staff / Head Maintenance"]
        Teacher["Teacher"]
    end

    subgraph Client["Client Layer (Browser)"]
        UI["Web UI<br/>Blade Templates"]
        JS["JavaScript<br/>FullCalendar, Bootstrap"]
    end

    subgraph App["Application Layer (Laravel)"]
        Auth["Auth Controller<br/>Login/Logout"]
        EventCtrl["Event Controller"]
        InvCtrl["Inventory Controller"]
        UserCtrl["User Controller"]
        ProfileCtrl["Profile Controller"]
        API["API Routes<br/>/api/events"]
    end

    subgraph Data["Data Layer"]
        DB[(PostgreSQL<br/>Supabase)]
    end

    subgraph Models["Models (Eloquent ORM)"]
        UserM["User"]
        EventM["Event"]
        EventItemM["EventItem"]
        InvM["InventoryItem"]
        EventHistM["EventHistory"]
    end

    External -->|HTTP Request| UI
    UI --> JS
    JS -->|AJAX| API
    UI --> Auth
    UI --> EventCtrl
    UI --> InvCtrl
    UI --> UserCtrl
    UI --> ProfileCtrl

    Auth --> UserM
    EventCtrl --> EventM
    EventCtrl --> EventItemM
    InvCtrl --> InvM
    UserCtrl --> UserM
    ProfileCtrl --> UserM
    API --> EventM

    EventM --> EventItemM
    EventItemM --> InvM
    EventM --> UserM
    EventM --> EventHistM

    UserM --> DB
    EventM --> DB
    EventItemM --> DB
    InvM --> DB
    EventHistM --> DB
```

### Dataflow Summary

| Source | Flow | Destination |
|--------|------|-------------|
| User (browser) | Login credentials | Auth Controller → User model → PostgreSQL |
| User | Event form (title, date, time, location, items) | Event Controller → Event + EventItems → PostgreSQL |
| Admin | Approve/Reject action | Event Controller → Updates Event status → Decreases Inventory |
| Staff/Admin | Inventory CRUD | Inventory Controller → InventoryItem → PostgreSQL |
| Admin | User management | User Controller → User → PostgreSQL |
| Staff/Admin | Return item | Event Controller → EventItem.returned_at, restores Inventory |
| Calendar | Fetch events | API → Event model → JSON response |

---

## 2. Authentication Flowchart

```mermaid
flowchart TD
    Start([User visits site]) --> LoginPage[Show Login Page]
    LoginPage --> EnterCreds[Enter Email & Password]
    EnterCreds --> Submit{Submit Form}
    Submit --> Validate{Valid credentials?}
    Validate -->|No| Error[Show Error Message]
    Error --> EnterCreds
    Validate -->|Yes| Redirect[Redirect to /home]
    Redirect --> Home[Show Dashboard]
    Home --> Logout{User logs out?}
    Logout -->|Yes| SessionEnd[End Session]
    SessionEnd --> LoginPage
    Logout -->|No| Nav[Navigate to feature]
    Nav --> AuthCheck{Authenticated?}
    AuthCheck -->|Yes| Feature[Access feature]
    AuthCheck -->|No| LoginPage
    Feature --> Nav
```

---

## 3. Event Creation Flowchart

```mermaid
flowchart TD
    Start([User clicks Create Event]) --> AuthCheck{Can create events?<br/>Admin/Heads only}
    AuthCheck -->|No| Deny[403 Unauthorized]
    AuthCheck -->|Yes| Form[Show Create Event Form]
    Form --> LoadInv[Load available inventory items]
    LoadInv --> FillForm[User fills title, date, time, location, items]
    FillForm --> Submit[Submit Form]
    Submit --> Validate{Validation OK?}
    Validate -->|No| ShowErrors[Show validation errors]
    ShowErrors --> FillForm
    Validate -->|Yes| Conflict{Time conflict with<br/>approved event?}
    Conflict -->|Yes| ConflictMsg[Show conflict error]
    ConflictMsg --> FillForm
    Conflict -->|No| CreateEvent[Create Event record]
    CreateEvent --> RoleCheck{User is Admin?}
    RoleCheck -->|Yes| AutoApprove[Set status = approved]
    RoleCheck -->|No| SetPending[Set status = pending]
    AutoApprove --> DecreaseInv[Decrease inventory quantity_available]
    SetPending --> SaveItems[Save event items pending approval]
    DecreaseInv --> SaveItems
    SaveItems --> Success[Redirect to Events List]
    Success --> End([Done])
```

---

## 4. Event Approval Flowchart (Admin)

```mermaid
flowchart TD
    Start([Admin views pending event]) --> ShowEvent[Show event details with items]
    ShowEvent --> Decision{Admin action?}
    Decision -->|Approve| ProcessApprove[Process Approval]
    Decision -->|Reject| EnterReason[Enter rejection reason]
    EnterReason --> RejectEvent[Set status = rejected]
    RejectEvent --> EndReject([Done])

    ProcessApprove --> CheckItems{Item-level decisions?}
    CheckItems -->|Yes| LoopItems[For each event item]
    CheckItems -->|No| ApproveAll[Approve all items by default]
    ApproveAll --> LoopItems

    LoopItems --> AvailCheck{Quantity available<br/>for event date?}
    AvailCheck -->|Yes| ApproveItem[Approve item, decrease inventory]
    AvailCheck -->|No| RejectItem[Reject item, qty_approved=0]
    ApproveItem --> MoreItems{More items?}
    RejectItem --> MoreItems
    MoreItems -->|Yes| LoopItems
    MoreItems -->|No| AnyApproved{At least one item<br/>approved?}
    AnyApproved -->|No| RejectAll[Reject entire event]
    RejectAll --> EndReject
    AnyApproved -->|Yes| UpdateEvent[Set event status = approved]
    UpdateEvent --> End([Done])
```

---

## 5. Inventory Management Flowchart

```mermaid
flowchart TD
    Start([User accesses Inventory]) --> AuthCheck{Can manage inventory?}
    AuthCheck -->|No| Deny[403 Unauthorized]
    AuthCheck -->|Yes| FilterCat{Has category<br/>restrictions?}
    FilterCat -->|Yes| FilteredList[Show only allowed categories]
    FilterCat -->|No| FullList[Show all inventory]
    FilteredList --> Action{User action?}
    FullList --> Action

    Action -->|Create| CreateForm[Show Create Form]
    CreateForm --> CreateCheck{User can create?<br/>Not restricted}
    CreateCheck -->|No| Deny
    CreateCheck -->|Yes| SaveNew[Save new item<br/>qty_available = qty_total]
    SaveNew --> End([Done])

    Action -->|Edit| EditForm[Show Edit Form]
    EditForm --> CatAccess{Can access this category?}
    CatAccess -->|No| Deny
    CatAccess -->|Yes| UpdateItem[Update item]
    UpdateItem --> End

    Action -->|Delete| BorrowedCheck{Item currently<br/>borrowed?}
    BorrowedCheck -->|Yes| BlockDelete[Block deletion]
    BorrowedCheck -->|No| SoftDelete[Soft delete item]
    SoftDelete --> End

    Action -->|Return Item| ReturnCheck{Item approved<br/>& not returned?}
    ReturnCheck -->|No| ReturnError[Show error]
    ReturnCheck -->|Yes| MarkReturned[Set returned_at]
    MarkReturned --> RestoreInv[Increment quantity_available]
    RestoreInv --> End
```

---

## 6. Item Return Flowchart

```mermaid
flowchart TD
    Start([Staff/Admin views event with items]) --> ShowItems[Show approved event items]
    ShowItems --> ReturnBtn{Click Return Item?}
    ReturnBtn -->|No| End([Done])
    ReturnBtn -->|Yes| CanReturn{User can confirm<br/>returns?}
    CanReturn -->|No| Deny[403 Unauthorized]
    CanReturn -->|Yes| AlreadyReturned{Already returned?}
    AlreadyReturned -->|Yes| Error[Show error]
    AlreadyReturned -->|No| TransStart[Begin DB transaction]
    TransStart --> SetReturned[Set event_item.returned_at = now]
    SetReturned --> RestoreQty[inventory_item.quantity_available += quantity_approved]
    RestoreQty --> Commit[Commit transaction]
    Commit --> Success[Show success message]
    Success --> End
```

---

## 7. User Management Flowchart (Admin Only)

```mermaid
flowchart TD
    Start([Admin accesses Users]) --> List[Show user list]
    List --> Action{Action?}
    Action -->|Create| CreateForm[Show create form]
    CreateForm --> ValidateUser[Validate name, email, role, password]
    ValidateUser --> SaveUser[Create user in DB]
    SaveUser --> End([Done])

    Action -->|Edit| EditForm[Load user, show edit form]
    EditForm --> UpdateUser[Update user]
    UpdateUser --> End

    Action -->|Delete| SelfCheck{Deleting self?}
    SelfCheck -->|Yes| Block[Block - cannot delete self]
    SelfCheck -->|No| NullEvents[Set created_by, approved_by to null<br/>on related events]
    NullEvents --> SoftDel[Soft delete user]
    SoftDel --> End
```

---

## 8. High-Level System Flow

```mermaid
flowchart LR
    subgraph Input
        A1[User Login]
        A2[Event Request]
        A3[Inventory Request]
        A4[User Mgmt]
    end

    subgraph Process
        B1[Authentication]
        B2[Event Controller]
        B3[Inventory Controller]
        B4[User Controller]
        B5[Role Check]
    end

    subgraph Storage
        C[(PostgreSQL<br/>Supabase)]
    end

    subgraph Output
        D1[Calendar View]
        D2[Event List]
        D3[Inventory List]
        D4[User List]
    end

    A1 --> B1
    A2 --> B2
    A3 --> B3
    A4 --> B4
    B1 --> B5
    B2 --> B5
    B3 --> B5
    B4 --> B5
    B5 --> C
    C --> D1
    C --> D2
    C --> D3
    C --> D4
```

---

## How to View These Diagrams

1. **GitHub**: Push this file to GitHub; Mermaid diagrams render automatically in markdown preview.
2. **VS Code**: Install the "Markdown Preview Mermaid Support" extension, then preview the file.
3. **Online**: Copy Mermaid code blocks into [mermaid.live](https://mermaid.live) to export as PNG/SVG.
4. **Documentation**: Use in your capstone report or presentation by exporting images from Mermaid Live Editor.

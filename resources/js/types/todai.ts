export type SuggestionConfidence = 'low' | 'medium' | 'high';

export type TaskSource = 'manual' | 'chat' | 'fireflies';

export type MeetingSource = 'fireflies' | 'manual';

export type MeetingStatus = 'draft' | 'processing' | 'ready' | 'failed';

export type SuggestionStatus = 'pending' | 'accepted' | 'dismissed';

export type Project = {
    id: string;
    name: string;
    description: string | null;
    color: string | null;
    archived_at: string | null;
    open_tasks_count?: number;
    created_at: string;
    updated_at: string;
};

export type TaskSuggestion = {
    id: string;
    meeting_id: string;
    title: string;
    description: string | null;
    due_date: string | null;
    for_me: boolean;
    status: SuggestionStatus;
    accepted_task_id: string | null;
    created_at: string;
    updated_at: string;
};

export type Meeting = {
    id: string;
    source: MeetingSource;
    fireflies_meeting_id: string | null;
    title: string | null;
    meeting_date: string | null;
    notes: string | null;
    summary: string | null;
    action_items: string | null;
    transcript: string | null;
    project_id: string | null;
    suggested_project_id: string | null;
    suggested_project_name: string | null;
    suggestion_confidence: SuggestionConfidence | null;
    suggestion_reasoning: string | null;
    status: MeetingStatus;
    error: string | null;
    processed_at: string | null;
    project?: Project | null;
    suggested_project?: Project | null;
    task_suggestions?: TaskSuggestion[];
    pending_suggestions_count?: number;
    created_at: string;
    updated_at: string;
};

export type Task = {
    id: string;
    project_id: string | null;
    title: string;
    description: string | null;
    due_date: string | null;
    completed_at: string | null;
    source: TaskSource;
    meeting_id: string | null;
    suggested_project_id: string | null;
    suggestion_confidence: SuggestionConfidence | null;
    suggestion_reasoning: string | null;
    project?: Project | null;
    suggested_project?: Project | null;
    meeting?: Meeting | null;
    created_at: string;
    updated_at: string;
};

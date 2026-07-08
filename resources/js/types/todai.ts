export type SuggestionConfidence = 'low' | 'medium' | 'high';

export type TaskSource = 'manual' | 'chat' | 'fireflies';

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

export type MeetingImport = {
    id: string;
    title: string | null;
    meeting_date: string | null;
    status: 'pending' | 'processed' | 'failed';
};

export type Task = {
    id: string;
    project_id: string | null;
    title: string;
    description: string | null;
    due_date: string | null;
    completed_at: string | null;
    source: TaskSource;
    meeting_import_id: string | null;
    suggested_project_id: string | null;
    suggestion_confidence: SuggestionConfidence | null;
    suggestion_reasoning: string | null;
    project?: Project | null;
    suggested_project?: Project | null;
    meeting_import?: MeetingImport | null;
    created_at: string;
    updated_at: string;
};

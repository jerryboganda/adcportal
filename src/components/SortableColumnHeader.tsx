import React from 'react';
import { ArrowUp, ArrowDown, ArrowUpDown } from 'lucide-react';
import { SortField, updateSortFields } from '../utils/tableUtils';

interface SortableColumnHeaderProps {
  column: string;
  label: string;
  sortFields: SortField[];
  onSortChange: (newSort: SortField[]) => void;
  className?: string;
  align?: 'left' | 'center' | 'right';
  tooltip?: string;
}

export const SortableColumnHeader: React.FC<SortableColumnHeaderProps> = ({
  column,
  label,
  sortFields,
  onSortChange,
  className = '',
  align = 'left',
  tooltip,
}) => {
  const sortIndex = sortFields.findIndex((f) => f.column === column);
  const activeSort = sortIndex >= 0 ? sortFields[sortIndex] : null;

  const handleClick = (e: React.MouseEvent) => {
    // If Shift is pressed or Cmd/Ctrl is held, perform multi-sort
    const multiSort = e.shiftKey || e.metaKey || e.ctrlKey;
    const nextSort = updateSortFields(sortFields, column, multiSort);
    onSortChange(nextSort);
  };

  const alignClass =
    align === 'right'
      ? 'justify-end text-right'
      : align === 'center'
      ? 'justify-center text-center'
      : 'justify-start text-left';

  return (
    <th
      className={`py-2.5 px-3 uppercase font-bold text-[11px] tracking-wider select-none cursor-pointer transition-colors hover:bg-slate-200/80 group ${className}`}
      onClick={handleClick}
      title={
        tooltip ||
        `Click to sort by ${label}. Hold Shift to add/remove as secondary sort.`
      }
    >
      <div className={`flex items-center space-x-1.5 ${alignClass}`}>
        <span className="group-hover:text-slate-900 transition-colors">{label}</span>
        
        {activeSort ? (
          <span className="inline-flex items-center space-x-0.5 text-cyan-800 bg-cyan-100/90 px-1 py-0.2 rounded border border-cyan-300 font-mono text-[9px] font-black">
            {sortFields.length > 1 && <span>{sortIndex + 1}</span>}
            {activeSort.direction === 'asc' ? (
              <ArrowUp className="w-3 h-3 text-cyan-700" />
            ) : (
              <ArrowDown className="w-3 h-3 text-cyan-700" />
            )}
          </span>
        ) : (
          <ArrowUpDown className="w-3 h-3 text-slate-300 group-hover:text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity" />
        )}
      </div>
    </th>
  );
};

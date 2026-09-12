def calculate_fibonacci(n):
    if n <= 0:
        return 0
    elif n == 1:
        return 1
    
    a, b = 0, 1
    for i in range(2, n + 1):
        a, b = b, a + b
    return b

def process_student_scores(scores_list):
    total = 0
    passed_students = []
    for score in scores_list:
        if score >= 60:
            passed_students.append(score)
            total += score
    average = total / len(passed_students) if len(passed_students) > 0 else 0
    return average, len(passed_students)

# Main execution
if __name__ == "__main__":
    result = calculate_fibonacci(10)
    print("Fibonacci result:", result)
